<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{DB, Env};

/**
 * Scratch cards.
 *  - PINs are random 12-digit numbers; only HMAC-SHA256(pin, APP_SECRET) is stored.
 *  - A card is bound to one school and one term, and allows MAX_USES successful result checks.
 *  - Redeeming is ONE conditional UPDATE inside a transaction (plus a row lock on MySQL), so parallel
 *    requests can never push use_count past max_uses. Failed lookups never reach that UPDATE.
 */
final class ScratchCardService
{
    public const MAX_USES = 5;
    public const PIN_LENGTH = 12;

    public static function normalizePin(string $pin): string
    {
        return preg_replace('/\D+/', '', $pin) ?? '';
    }

    public static function hashPin(string $pin): string
    {
        return hash_hmac('sha256', self::normalizePin($pin), Env::secret());
    }

    public static function formatPin(string $digits): string
    {
        return implode('-', str_split($digits, 4));
    }

    public static function randomDigits(int $n): string
    {
        $s = '';
        for ($i = 0; $i < $n; $i++) {
            $s .= (string) random_int(0, 9);
        }
        return $s;
    }

    /** SQL expression for a card's real status (an active card past its expiry date is "expired"). */
    public static function effectiveStatusSql(string $alias = 'c'): string
    {
        return "CASE WHEN $alias.status = 'active' AND $alias.expires_at IS NOT NULL AND $alias.expires_at <= ? THEN 'expired' ELSE $alias.status END";
    }

    /**
     * Creates a batch. The plaintext PINs are returned ONCE and never stored.
     * @return array{batch_id:int,cards:array<int,array{serial:string,pin:string}>}
     */
    public static function generateBatch(int $sid, int $termId, int $qty, ?string $label, ?int $userId, ?string $expiresAt, ?int $classId = null, ?int $requestId = null): array
    {
        $term = DB::one('SELECT id, session_id FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]);
        if ($term === null) {
            throw new \RuntimeException('Unknown term.');
        }
        if ($qty < 1 || $qty > 1000) {
            throw new \RuntimeException('A batch must contain between 1 and 1000 cards.');
        }
        if ($classId !== null && DB::val('SELECT COUNT(*) FROM classes WHERE id = ? AND school_id = ?', [$classId, $sid]) == 0) { throw new \RuntimeException('The selected class does not belong to this school.'); }
        return DB::transaction(static function () use ($sid, $term, $termId, $qty, $label, $userId, $expiresAt, $classId, $requestId): array {
            $batchId = DB::insert(
                'INSERT INTO scratch_card_batches (school_id, session_id, term_id, class_id, request_id, quantity, label, created_by) VALUES (?,?,?,?,?,?,?,?)',
                [$sid, $term['session_id'], $termId, $classId, $requestId, $qty, $label, $userId]
            );
            $cards = [];
            for ($i = 0; $i < $qty; $i++) {
                for ($attempt = 0; ; $attempt++) {
                    $pin = self::randomDigits(self::PIN_LENGTH);
                    $serial = 'GL' . self::randomDigits(10);
                    try {
                        DB::insert(
                            'INSERT INTO scratch_cards (school_id, batch_id, session_id, term_id, serial, pin_hash, max_uses, expires_at) VALUES (?,?,?,?,?,?,?,?)',
                            [$sid, $batchId, $term['session_id'], $termId, $serial, self::hashPin($pin), self::MAX_USES, $expiresAt]
                        );
                        break;
                    } catch (\PDOException $e) {
                        if ($attempt >= 5 || !str_starts_with((string) $e->getCode(), '23')) {
                            throw $e;
                        }
                        // serial or PIN collision: draw again
                    }
                }
                $cards[] = ['serial' => $serial, 'pin' => $pin];
            }
            return ['batch_id' => $batchId, 'cards' => $cards];
        });
    }

    /** @return array<string,int> counts by effective status for a school (optionally one term/batch) */
    public static function stats(int $sid, ?int $termId = null, ?int $batchId = null): array
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'SELECT ' . self::effectiveStatusSql('c') . ' AS st, CASE WHEN c.use_count = 0 THEN 0 ELSE 1 END AS touched, COUNT(*) AS n FROM scratch_cards c WHERE c.school_id = ?';
        $p = [$now, $sid];
        if ($termId !== null) {
            $sql .= ' AND c.term_id = ?';
            $p[] = $termId;
        }
        if ($batchId !== null) {
            $sql .= ' AND c.batch_id = ?';
            $p[] = $batchId;
        }
        $out = ['total' => 0, 'unused' => 0, 'in_use' => 0, 'exhausted' => 0, 'revoked' => 0, 'expired' => 0];
        foreach (DB::all($sql . ' GROUP BY st, touched', $p) as $r) {
            $n = (int) $r['n'];
            $out['total'] += $n;
            if ($r['st'] === 'active') {
                $out[(int) $r['touched'] === 0 ? 'unused' : 'in_use'] += $n;
            } else {
                $out[$r['st']] += $n;
            }
        }
        return $out;
    }

    /**
     * Validates everything, then consumes one use atomically.
     *
     * @return array{ok:bool,code:?string,error:?string,student_id:?int,remaining:?int}
     */
    public static function redeem(int $sid, int $termId, string $admissionNo, string $pin, string $ip, string $userAgent): array
    {
        $fail = static fn(string $code, string $msg): array => ['ok' => false, 'code' => $code, 'error' => $msg, 'student_id' => null, 'remaining' => null];

        $digits = self::normalizePin($pin);
        if (strlen($digits) !== self::PIN_LENGTH) {
            return $fail('invalid_card', 'Enter the ' . self::PIN_LENGTH . '-digit PIN printed on your scratch card.');
        }
        $card = DB::one('SELECT c.*, b.class_id AS batch_class_id FROM scratch_cards c JOIN scratch_card_batches b ON b.id = c.batch_id AND b.school_id = c.school_id WHERE c.pin_hash = ?', [self::hashPin($digits)]);
        if ($card === null) {
            return $fail('invalid_card', 'That PIN is not valid. Check it and try again.');
        }
        if ((int) $card['school_id'] !== $sid || (int) $card['term_id'] !== $termId) {
            return $fail('wrong_scope', 'This card was not issued for the selected school and term.');
        }
        $now = date('Y-m-d H:i:s');
        $reason = self::unusable($card, $now);
        if ($reason !== null) {
            return $reason;
        }

        $student = DB::one(
            "SELECT id, class_id FROM students WHERE school_id = ? AND LOWER(admission_no) = LOWER(?) AND status = 'active'",
            [$sid, trim($admissionNo)]
        );
        if ($student === null) {
            return $fail('student_not_found', 'No student with that ID was found in this school.');
        }
        $studentId = (int) $student['id'];
        $published = DB::one("SELECT class_id FROM result_summaries WHERE school_id = ? AND term_id = ? AND student_id = ? AND status = 'published'", [$sid, $termId, $studentId]);
        if ($published === null) {
            return $fail('not_published', 'Results for this student and term are not available yet.');
        }
        if ($card['batch_class_id'] !== null && (int) $card['batch_class_id'] !== (int) $published['class_id']) {
            return $fail('wrong_class', 'This result-checking PIN was purchased for a different class. Please use the PIN issued for your class.');
        }
        $bind = DB::val('SELECT card_binding FROM school_settings WHERE school_id = ?', [$sid]) === 'first_student';
        if ($bind && $card['bound_student_id'] !== null && (int) $card['bound_student_id'] !== $studentId) {
            return $fail('wrong_student', 'This card has already been used by another student.');
        }

        // ---- atomic consumption ----
        $ok = DB::transaction(static function () use ($card, $studentId, $bind, $now, $sid, $termId, $ip, $userAgent): bool {
            if (DB::isMysql()) {
                DB::one('SELECT id FROM scratch_cards WHERE id = ? FOR UPDATE', [$card['id']]); // serialise parallel redemptions
            }
            // NB: `status` is assigned BEFORE `use_count` on purpose: MySQL evaluates SET left to right.
            $sql = "UPDATE scratch_cards SET
                        status = CASE WHEN use_count + 1 >= max_uses THEN 'exhausted' ELSE status END,
                        use_count = use_count + 1,
                        last_used_at = ?, first_used_at = COALESCE(first_used_at, ?)"
                . ($bind ? ', bound_student_id = COALESCE(bound_student_id, ?)' : '')
                . " WHERE id = ? AND status = 'active' AND use_count < max_uses AND (expires_at IS NULL OR expires_at > ?)"
                . ($bind ? ' AND (bound_student_id IS NULL OR bound_student_id = ?)' : '');
            $params = [$now, $now];
            if ($bind) {
                $params[] = $studentId;
            }
            array_push($params, $card['id'], $now);
            if ($bind) {
                $params[] = $studentId;
            }
            if (DB::exec($sql, $params) !== 1) {
                return false;
            }
            DB::insert(
                'INSERT INTO scratch_card_usages (card_id, school_id, term_id, student_id, ip, user_agent, used_at) VALUES (?,?,?,?,?,?,?)',
                [$card['id'], $sid, $termId, $studentId, substr($ip, 0, 45), substr($userAgent, 0, 255), $now]
            );
            return true;
        });

        if (!$ok) {
            // Lost a race or the card changed state between the checks and the update; explain why. Nothing was consumed.
            $fresh = DB::one('SELECT * FROM scratch_cards WHERE id = ?', [$card['id']]);
            $why = $fresh !== null ? self::unusable($fresh, $now) : null;
            if ($why !== null) {
                return $why;
            }
            return $fail('wrong_student', 'This card could not be used for that student.');
        }
        $after = DB::one('SELECT use_count, max_uses FROM scratch_cards WHERE id = ?', [$card['id']]);
        return ['ok' => true, 'code' => null, 'error' => null, 'student_id' => $studentId, 'remaining' => (int) $after['max_uses'] - (int) $after['use_count']];
    }

    /** @return ?array{ok:bool,code:string,error:string,student_id:?int,remaining:?int} */
    private static function unusable(array $card, string $now): ?array
    {
        $r = static fn(string $code, string $msg): array => ['ok' => false, 'code' => $code, 'error' => $msg, 'student_id' => null, 'remaining' => null];
        if ($card['status'] === 'revoked') {
            return $r('revoked', 'This card has been cancelled. Please contact the school.');
        }
        if ($card['status'] === 'exhausted' || (int) $card['use_count'] >= (int) $card['max_uses']) {
            return $r('exhausted', 'This card has already been used the maximum number of times (' . $card['max_uses'] . '). Please obtain a new card.');
        }
        if ($card['status'] === 'expired' || ($card['expires_at'] !== null && $card['expires_at'] <= $now)) {
            return $r('expired', 'This card has expired.');
        }
        return null;
    }
}
