<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Http, View};
use App\Services\{Notify, Platform, ScratchCardService};

/** School administrator: scratch-card batches, inventory, revocation and exports. */
final class CardsController extends BaseController
{
    private const BACK = '/school/cards';
    private const PER_PAGE = 50;

    private function terms(): array
    {
        return DB::all(
            'SELECT t.id, t.name, t.is_current, s.name AS session_name FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.school_id = ? ORDER BY s.name DESC, t.id DESC',
            [$this->sid()]
        );
    }

    public function index(): void
    {
        $sid = $this->sid();
        $now = date('Y-m-d H:i:s');
        $batches = DB::all(
            "SELECT b.*, t.name AS term_name, s.name AS session_name, cl.name AS class_name,
                    (SELECT COUNT(*) FROM scratch_cards c WHERE c.batch_id = b.id AND c.use_count = 0 AND c.status = 'active') AS unused,
                    (SELECT COUNT(*) FROM scratch_cards c WHERE c.batch_id = b.id AND c.use_count > 0) AS touched,
                    (SELECT COUNT(*) FROM scratch_cards c WHERE c.batch_id = b.id AND c.status = 'revoked') AS revoked
             FROM scratch_card_batches b
             JOIN terms t ON t.id = b.term_id AND t.school_id = b.school_id
             JOIN academic_sessions s ON s.id = b.session_id AND s.school_id = b.school_id LEFT JOIN classes cl ON cl.id = b.class_id AND cl.school_id = b.school_id
             WHERE b.school_id = ? ORDER BY b.id DESC LIMIT 100",
            [$sid]
        );
        $requests = DB::all('SELECT pr.*, t.name AS term_name, se.name AS session_name, c.name AS class_name FROM pin_requests pr JOIN terms t ON t.id = pr.term_id AND t.school_id = pr.school_id JOIN academic_sessions se ON se.id = t.session_id AND se.school_id = t.school_id LEFT JOIN classes c ON c.id = pr.class_id AND c.school_id = pr.school_id WHERE pr.school_id = ? ORDER BY pr.id DESC LIMIT 30', [$sid]);
        $approved = array_values(array_filter($requests, static fn($r) => $r['status'] === 'approved'));
        $classes = DB::all("SELECT c.id, c.name, (SELECT COUNT(*) FROM students st WHERE st.school_id = c.school_id AND st.class_id = c.id AND st.status = 'active') AS student_count FROM classes c WHERE c.school_id = ? ORDER BY c.level_order, c.name", [$sid]);
        $schoolStudentCount = (int) DB::val("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'", [$sid]);
        View::render('school/cards', ['title' => 'Scratch cards', 'terms' => $this->terms(), 'batches' => $batches, 'stats' => ScratchCardService::stats($sid), 'now' => $now, 'requests' => $requests, 'approvedRequests' => $approved, 'classes' => $classes, 'schoolStudentCount' => $schoolStudentCount, 'pinPrice' => (float) Platform::get('pin_price_per_student')]);
    }

    public function requestPins(): void
    {
        $term = $this->owned('terms', (int) Http::input('term_id'));
        $classId = (int) Http::input('class_id') ?: null; // null means the whole school
        if ($classId !== null && DB::val('SELECT COUNT(*) FROM classes WHERE id = ? AND school_id = ?', [$classId, $this->sid()]) == 0) {
            $this->fail('Choose a valid class.', self::BACK);
        }
        $quantity = $classId !== null
            ? (int) DB::val('SELECT COUNT(DISTINCT student_id) FROM result_summaries WHERE school_id = ? AND term_id = ? AND class_id = ?', [$this->sid(), $term['id'], $classId])
            : (int) DB::val('SELECT COUNT(DISTINCT student_id) FROM result_summaries WHERE school_id = ? AND term_id = ?', [$this->sid(), $term['id']]);
        if ($quantity === 0) {
            $quantity = (int) DB::val(
                "SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'" . ($classId !== null ? ' AND class_id = ?' : ''),
                $classId !== null ? [$this->sid(), $classId] : [$this->sid()]
            );
        }
        $pendingSql = "SELECT COUNT(*) FROM pin_requests WHERE school_id = ? AND term_id = ? AND status IN ('pending','approved')" . ($classId === null ? ' AND class_id IS NULL' : ' AND class_id = ?');
        $pendingParams = $classId === null ? [$this->sid(), $term['id']] : [$this->sid(), $term['id'], $classId];
        if ((int) DB::val($pendingSql, $pendingParams) > 0) { $this->fail('There is already a pending or approved PIN request for this term and coverage. Complete or resolve it before submitting another.', self::BACK); }
        if ($quantity < 1) { $this->fail('There are no active students in the selected scope.', self::BACK); }
        $price = (float) Platform::get('pin_price_per_student');
        if ($price <= 0) { $this->fail('Result-checking PIN pricing has not been configured by the platform administrator yet.', self::BACK); }
        $total = round($price * $quantity, 2);
        $id = DB::insert(
            "INSERT INTO pin_requests (school_id, term_id, class_id, quantity, unit_price, total_amount, status, requested_by) VALUES (?,?,?,?,?,?,'pending',?)",
            [$this->sid(), $term['id'], $classId, $quantity, $price, $total, (int) Auth::user()['id']]
        );
        AuditLog::record('pins.requested', 'pin_request', $id, ['quantity' => $quantity, 'amount' => $total, 'class_id' => $classId]);
        $this->ok("PIN request #$id submitted for $quantity student(s). The platform administrator must approve it after payment verification.", self::BACK);
    }

    public function generate(): void
    {
        $requestId = (int) Http::input('request_id');
        $request = DB::one("SELECT * FROM pin_requests WHERE id = ? AND school_id = ? AND status = 'approved'", [$requestId, $this->sid()]);
        if ($request === null) { $this->fail('Choose an approved PIN request. Requests must be approved before cards can be generated.', self::BACK); }
        $exp = Http::input('expires_on');
        if ($exp !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) || $exp < date('Y-m-d'))) {
            $this->fail('The expiry date must be today or later.', self::BACK);
        }
        try {
            $res = DB::transaction(static function () use ($request, $requestId, $exp): array {
                $lock = 'SELECT * FROM pin_requests WHERE id = ? AND school_id = ?'; if (DB::isMysql()) { $lock .= ' FOR UPDATE'; }
                $fresh = DB::one($lock, [$requestId, $request['school_id']]);
                if ($fresh === null || $fresh['status'] !== 'approved') { throw new \RuntimeException('This PIN request is no longer available for fulfilment.'); }
                $result = ScratchCardService::generateBatch((int) $fresh['school_id'], (int) $fresh['term_id'], (int) $fresh['quantity'], 'Approved PIN request #' . $requestId, (int) Auth::user()['id'], $exp !== '' ? $exp . ' 23:59:59' : null, $fresh['class_id'] !== null ? (int) $fresh['class_id'] : null, $requestId);
                DB::run("UPDATE pin_requests SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ? AND status = 'approved'", [$requestId]);
                return $result;
            });
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), self::BACK);
        }
        $_SESSION['new_pins'] = [$res['batch_id'] => $res['cards']];
        AuditLog::record('cards.generated', 'batch', $res['batch_id'], ['request_id' => $requestId, 'quantity' => (int) $request['quantity']]);
        Notify::schoolAdmins($this->sid(), 'Scratch cards generated', (int) $request['quantity'] . ' result-checking PIN(s) have been generated. Save the PINs now.');
        $this->ok((int) $request['quantity'] . ' approved scratch cards generated. Save the PINs now: they cannot be shown again.', '/school/cards/batches/' . $res['batch_id']);
    }

    private function batch(int $id): array
    {
        $b = DB::one(
            'SELECT b.*, t.name AS term_name, s.name AS session_name, cl.name AS class_name FROM scratch_card_batches b
             JOIN terms t ON t.id = b.term_id AND t.school_id = b.school_id JOIN academic_sessions s ON s.id = b.session_id AND s.school_id = b.school_id LEFT JOIN classes cl ON cl.id = b.class_id AND cl.school_id = b.school_id
             WHERE b.id = ? AND b.school_id = ?',
            [$id, $this->sid()]
        );
        if ($b === null) {
            Http::abort(404);
        }
        return $b;
    }

    public function showBatch(int $id): void
    {
        $sid = $this->sid();
        $b = $this->batch($id);
        $status = Http::input('status');
        $page = max(1, (int) Http::input('page', '1'));
        $now = date('Y-m-d H:i:s');
        $eff = ScratchCardService::effectiveStatusSql('c');
        $where = 'c.batch_id = ? AND c.school_id = ?';
        $p = [$id, $sid];
        if (in_array($status, ['active', 'exhausted', 'revoked', 'expired'], true)) {
            $where .= " AND ($eff) = ?";
            array_push($p, $now, $status);
        } elseif ($status === 'unused') {
            $where .= " AND c.status = 'active' AND c.use_count = 0";
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM scratch_cards c WHERE $where", $p);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;
        $cards = DB::all(
            "SELECT c.id, c.serial, c.use_count, c.max_uses, c.first_used_at, c.last_used_at, c.expires_at, ($eff) AS eff_status, st.first_name, st.last_name, st.admission_no
             FROM scratch_cards c LEFT JOIN students st ON st.id = c.bound_student_id AND st.school_id = c.school_id
             WHERE $where ORDER BY c.id LIMIT " . self::PER_PAGE . " OFFSET $offset",
            array_merge([$now], $p)
        );
        $hasPins = isset($_SESSION['new_pins'][$id]);
        View::render('school/card_batch', [
            'title' => 'Batch #' . $id, 'b' => $b, 'cards' => $cards, 'stats' => ScratchCardService::stats($sid, null, $id),
            'status' => $status, 'page' => $page, 'pages' => $pages, 'total' => $total, 'pins' => $hasPins ? $_SESSION['new_pins'][$id] : null,
        ]);
    }

    public function slips(int $id): void
    {
        $b = $this->batch($id);
        $pins = $_SESSION['new_pins'][$id] ?? null;
        if ($pins === null) {
            Http::abort(404, 'PINs can only be printed right after generation. They are not stored in readable form.');
        }
        $school = DB::one('SELECT name FROM schools WHERE id = ?', [$this->sid()]);
        AuditLog::record('cards.slips_printed', 'batch', $id);
        View::render('school/card_slips', ['title' => 'Scratch cards - batch ' . $id, 'b' => $b, 'pins' => $pins, 'school' => $school], 'report');
    }

    public function pinsCsv(int $id): void
    {
        $b = $this->batch($id);
        $pins = $_SESSION['new_pins'][$id] ?? null;
        if ($pins === null) {
            Http::abort(404, 'PINs can only be exported right after generation. They are not stored in readable form.');
        }
        unset($_SESSION['new_pins'][$id]); // one download only
        AuditLog::record('cards.pins_exported', 'batch', $id, ['count' => count($pins)]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scratch-cards-batch-' . $id . '.csv"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, ['serial', 'pin', 'term', 'session', 'valid_for_checks'], ',', '"', '');
        foreach ($pins as $c) {
            fputcsv($h, [$c['serial'], ScratchCardService::formatPin($c['pin']), $b['term_name'], $b['session_name'], ScratchCardService::MAX_USES], ',', '"', '');
        }
        fclose($h);
        exit;
    }

    public function discardPins(int $id): void
    {
        $this->batch($id);
        unset($_SESSION['new_pins'][$id]);
        $this->ok('The PIN list has been cleared from this session.', '/school/cards/batches/' . $id);
    }

    public function exportInventory(): void
    {
        $sid = $this->sid();
        $now = date('Y-m-d H:i:s');
        $eff = ScratchCardService::effectiveStatusSql('c');
        $where = 'c.school_id = ?';
        $p = [$now, $sid];
        $termId = (int) Http::input('term_id');
        if ($termId > 0) {
            $where .= ' AND c.term_id = ?';
            $p[] = $termId;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scratch-card-inventory.csv"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, ['serial', 'session', 'term', 'status', 'uses', 'max_uses', 'used_by_student_id', 'first_used', 'last_used', 'expires', 'batch', 'created'], ',', '"', '');
        foreach (DB::all(
            "SELECT c.serial, c.use_count, c.max_uses, c.first_used_at, c.last_used_at, c.expires_at, c.batch_id, c.created_at, ($eff) AS eff,
                    t.name AS term_name, s.name AS session_name, st.admission_no
             FROM scratch_cards c JOIN terms t ON t.id = c.term_id AND t.school_id = c.school_id JOIN academic_sessions s ON s.id = c.session_id AND s.school_id = c.school_id
             LEFT JOIN students st ON st.id = c.bound_student_id AND st.school_id = c.school_id WHERE $where ORDER BY c.id",
            $p
        ) as $r) {
            fputcsv($h, array_map('csv_safe', [$r['serial'], $r['session_name'], $r['term_name'], $r['eff'], $r['use_count'], $r['max_uses'], $r['admission_no'], $r['first_used_at'], $r['last_used_at'], $r['expires_at'], $r['batch_id'], $r['created_at']]), ',', '"', '');
        }
        fclose($h);
        AuditLog::record('cards.inventory_exported', 'card', null);
        exit;
    }

    public function showCard(int $id): void
    {
        $sid = $this->sid();
        $c = DB::one(
            'SELECT c.*, t.name AS term_name, s.name AS session_name, st.first_name, st.last_name, st.admission_no
             FROM scratch_cards c JOIN terms t ON t.id = c.term_id AND t.school_id = c.school_id JOIN academic_sessions s ON s.id = c.session_id AND s.school_id = c.school_id
             LEFT JOIN students st ON st.id = c.bound_student_id AND st.school_id = c.school_id WHERE c.id = ? AND c.school_id = ?',
            [$id, $sid]
        );
        if ($c === null) {
            Http::abort(404);
        }
        $usages = DB::all(
            'SELECT u.used_at, u.ip, u.user_agent, st.first_name, st.last_name, st.admission_no FROM scratch_card_usages u
             JOIN students st ON st.id = u.student_id AND st.school_id = u.school_id WHERE u.card_id = ? AND u.school_id = ? ORDER BY u.id',
            [$id, $sid]
        );
        View::render('school/card_show', ['title' => 'Card ' . $c['serial'], 'c' => $c, 'usages' => $usages]);
    }

    public function revoke(int $id): void
    {
        $n = DB::exec("UPDATE scratch_cards SET status = 'revoked', revoked_at = ? WHERE id = ? AND school_id = ? AND status IN ('active','expired')", [date('Y-m-d H:i:s'), $id, $this->sid()]);
        if ($n === 0) {
            $this->fail('That card could not be revoked (it may already be exhausted or revoked).', "/school/cards/$id");
        }
        AuditLog::record('card.revoked', 'card', $id);
        $this->ok('Card revoked. It can no longer be used.', "/school/cards/$id");
    }

    public function revokeBatch(int $id): void
    {
        $this->batch($id);
        $n = DB::exec("UPDATE scratch_cards SET status = 'revoked', revoked_at = ? WHERE batch_id = ? AND school_id = ? AND status = 'active' AND use_count = 0", [date('Y-m-d H:i:s'), $id, $this->sid()]);
        AuditLog::record('batch.revoked', 'batch', $id, ['revoked' => $n]);
        $this->ok("$n unused card(s) revoked. Cards that were already used were left active.", "/school/cards/batches/$id");
    }
}
