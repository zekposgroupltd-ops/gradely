<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Http, View};
use App\Services\{Notify, Passwords, Platform, ScratchCardService};

/** Super Administrator: activity logs, scratch-card monitoring, platform admins, broadcasts and settings. */
final class SuperToolsController extends BaseController
{
    private const PER_PAGE = 50;

    public function logs(): void
    {
        $schoolId = (int) Http::input('school_id');
        $action = Http::input('action');
        $page = max(1, (int) Http::input('page', '1'));
        $where = '1 = 1';
        $p = [];
        if ($schoolId > 0) {
            $where .= ' AND a.school_id = ?';
            $p[] = $schoolId;
        }
        if ($action !== '') {
            $where .= ' AND a.action LIKE ?';
            $p[] = $action . '%';
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM audit_logs a WHERE $where", $p);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = DB::all(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.meta, a.ip, a.created_at, u.name AS user_name, s.name AS school_name
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN schools s ON s.id = a.school_id
             WHERE $where ORDER BY a.id DESC LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $p
        );
        View::render('super/logs', [
            'title' => 'Activity logs', 'rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total,
            'schools' => DB::all('SELECT id, name FROM schools ORDER BY name'), 'schoolId' => $schoolId, 'action' => $action,
        ]);
    }

    public function cards(): void
    {
        $now = date('Y-m-d H:i:s');
        $perSchool = [];
        foreach (DB::all('SELECT id, name FROM schools ORDER BY name') as $s) {
            $perSchool[] = ['school' => $s['name'], 'id' => $s['id'], 'stats' => ScratchCardService::stats((int) $s['id'])];
        }
        $totals = ['total' => 0, 'unused' => 0, 'in_use' => 0, 'exhausted' => 0, 'revoked' => 0, 'expired' => 0];
        foreach ($perSchool as $r) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $r['stats'][$k];
            }
        }
        $recent = DB::all(
            'SELECT u.used_at, u.ip, c.serial, s.name AS school_name, t.name AS term_name, st.admission_no
             FROM scratch_card_usages u JOIN scratch_cards c ON c.id = u.card_id JOIN schools s ON s.id = u.school_id
             JOIN terms t ON t.id = u.term_id AND t.school_id = u.school_id JOIN students st ON st.id = u.student_id AND st.school_id = u.school_id
             ORDER BY u.id DESC LIMIT 20'
        );
        View::render('super/cards', ['title' => 'Scratch-card monitor', 'perSchool' => $perSchool, 'totals' => $totals, 'recent' => $recent]);
    }

    public function pinRequests(): void
    {
        $rows = DB::all("SELECT pr.*, s.name AS school_name, t.name AS term_name, se.name AS session_name, c.name AS class_name, u.name AS requester_name FROM pin_requests pr JOIN schools s ON s.id = pr.school_id JOIN terms t ON t.id = pr.term_id AND t.school_id = pr.school_id JOIN academic_sessions se ON se.id = t.session_id AND se.school_id = t.school_id LEFT JOIN classes c ON c.id = pr.class_id AND c.school_id = pr.school_id LEFT JOIN users u ON u.id = pr.requested_by WHERE pr.status IN ('pending','approved','rejected','fulfilled') ORDER BY CASE pr.status WHEN 'pending' THEN 0 ELSE 1 END, pr.id DESC LIMIT 200");
        View::render('super/pin_requests', ['title' => 'PIN purchase requests', 'rows' => $rows, 'price' => (float) Platform::get('pin_price_per_student')]);
    }

    public function approvePinRequest(int $id): void
    {
        $note = $this->text('review_note', 255) ?? '';
        $request = DB::one("SELECT * FROM pin_requests WHERE id = ? AND status = 'pending'", [$id]);
        if ($request === null) { $this->fail('This request is no longer pending.', '/super/pin-requests'); }
        try {
            DB::transaction(static function () use ($id, $note): void {
                $sql = 'SELECT id, status FROM pin_requests WHERE id = ?'; if (DB::isMysql()) { $sql .= ' FOR UPDATE'; }
                $r = DB::one($sql, [$id]);
                if ($r === null || $r['status'] !== 'pending') { throw new \RuntimeException('Request has already been reviewed.'); }
                DB::run("UPDATE pin_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?", [(int) Auth::user()['id'], $note ?: null, $id]);
            });
        } catch (\RuntimeException $e) { $this->fail($e->getMessage(), '/super/pin-requests'); }
        Notify::schoolAdmins((int) $request['school_id'], 'PIN request approved', 'Your request for ' . (int) $request['quantity'] . ' result-checking PIN(s) has been approved. You can now generate the PINs from Scratch Cards.');
        AuditLog::record('pins.request_approved', 'pin_request', $id, ['quantity' => (int) $request['quantity'], 'amount' => $request['total_amount']]);
        $this->ok('PIN request approved. The school can now generate its allocated PINs.', '/super/pin-requests');
    }

    public function rejectPinRequest(int $id): void
    {
        $note = $this->text('review_note', 255) ?? '';
        $request = DB::one("SELECT * FROM pin_requests WHERE id = ? AND status = 'pending'", [$id]);
        if ($request === null) { $this->fail('This request is no longer pending.', '/super/pin-requests'); }
        $changed = DB::exec("UPDATE pin_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ? AND status = 'pending'", [(int) Auth::user()['id'], $note ?: null, $id]);
        if ($changed !== 1) { $this->fail('This request has already been reviewed.', '/super/pin-requests'); }
        Notify::schoolAdmins((int) $request['school_id'], 'PIN request not approved', 'Your request for ' . (int) $request['quantity'] . ' result-checking PIN(s) was not approved. Contact platform support if you need clarification.');
        AuditLog::record('pins.request_rejected', 'pin_request', $id, ['note' => $note]);
        $this->ok('PIN request rejected.', '/super/pin-requests');
    }

    public function admins(): void
    {
        View::render('super/admins', [
            'title' => 'Platform administrators',
            'admins' => DB::all("SELECT id, name, email, status, last_login_at, created_at FROM users WHERE role = 'super_admin' ORDER BY id"),
        ]);
    }

    public function storeAdmin(): void
    {
        $name = $this->text('name', 150);
        $email = strtolower((string) $this->text('email'));
        $pw = (string) ($_POST['password'] ?? '');
        $old = ['name' => $name, 'email' => $email];
        if ($name === null || $name === '') {
            $this->fail('Enter a name.', '/super/admins', $old);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $this->fail('Enter a valid email address.', '/super/admins', $old);
        }
        if ((int) DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $this->fail('That email is already in use.', '/super/admins', $old);
        }
        $generated = $pw === '';
        if ($generated) {
            $pw = Passwords::generate();
        } elseif (strlen($pw) < Auth::MIN_PASSWORD) {
            $this->fail('The password must be at least ' . Auth::MIN_PASSWORD . ' characters (or leave it blank to generate one).', '/super/admins', $old);
        }
        $id = DB::insert("INSERT INTO users (school_id, name, email, password_hash, role) VALUES (NULL, ?, ?, ?, 'super_admin')", [$name, $email, Auth::hash($pw)]);
        AuditLog::record('platform_admin.created', 'user', $id, ['email' => $email]);
        $this->ok("Administrator $email created." . ($generated ? " Temporary password: $pw (shown once, copy it now)." : ''), '/super/admins');
    }

    public function toggleAdmin(int $id): void
    {
        $u = DB::one("SELECT id, email, status FROM users WHERE id = ? AND role = 'super_admin'", [$id]);
        if ($u === null) {
            Http::abort(404);
        }
        if ($id === (int) Auth::user()['id']) {
            $this->fail('You cannot disable your own account.', '/super/admins');
        }
        $new = $u['status'] === 'active' ? 'disabled' : 'active';
        DB::run('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
        AuditLog::record('platform_admin.' . $new, 'user', $id);
        $this->ok($u['email'] . ' is now ' . $new . '.', '/super/admins');
    }

    public function notifications(): void
    {
        View::render('super/notify', ['title' => 'Notifications & support', 'schools' => DB::all("SELECT id, name FROM schools WHERE status = 'active' ORDER BY name")]);
    }

    public function sendNotification(): void
    {
        $title = $this->text('title', 150);
        $body = $this->text('body', 500);
        $target = (int) Http::input('school_id'); // 0 = every active school
        if ($title === null || $title === '' || $body === null) {
            $this->fail('Enter a title (up to 150 characters) and a message (up to 500).', '/super/notifications');
        }
        $schools = $target > 0
            ? DB::all("SELECT id FROM schools WHERE id = ? AND status = 'active'", [$target])
            : DB::all("SELECT id FROM schools WHERE status = 'active'");
        if (!$schools) {
            $this->fail('No active school matches that selection.', '/super/notifications');
        }
        DB::transaction(static function () use ($schools, $title, $body): void {
            foreach ($schools as $s) {
                Notify::schoolAdmins((int) $s['id'], $title, $body ?: null);
            }
        });
        AuditLog::record('notification.broadcast', 'school', $target ?: null, ['title' => $title, 'schools' => count($schools)]);
        $this->ok('Notification sent to ' . count($schools) . ' school(s).', '/super/notifications');
    }

    public function settings(): void
    {
        View::render('super/settings', ['title' => 'Platform settings', 'cfg' => Platform::all()]);
    }

    public function saveSettings(): void
    {
        $email = (string) $this->text('support_email');
        $phone = (string) $this->text('support_phone', 40);
        $ann = $this->text('announcement', 400);
        $pinPrice = trim(Http::input('pin_price_per_student'));
        if ($pinPrice === '' || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $pinPrice) || (float) $pinPrice < 0) {
            $this->fail('Enter a valid non-negative PIN price per student.', '/super/settings');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('The support email is not valid.', '/super/settings');
        }
        if ($ann === null) {
            $this->fail('The announcement is too long (400 characters).', '/super/settings');
        }
        Platform::set('support_email', $email);
        Platform::set('support_phone', $phone);
        Platform::set('announcement', $ann);
        Platform::set('pin_price_per_student', number_format((float) $pinPrice, 2, '.', ''));
        AuditLog::record('platform.settings_updated', 'platform_settings');
        $this->ok('Platform settings saved.', '/super/settings');
    }
}
