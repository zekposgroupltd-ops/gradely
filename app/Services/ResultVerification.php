<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Issues immutable, revocable authenticity codes for the exact published report snapshot. */
final class ResultVerification
{
    public static function snapshot(array $report): string
    {
        unset($report['verification']);
        $normalise = static function (&$value) use (&$normalise): void {
            if (!is_array($value)) return;
            if (!array_is_list($value)) ksort($value);
            foreach ($value as &$item) $normalise($item);
        };
        $normalise($report);
        return hash('sha256', json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    public static function issue(array $report): array
    {
        $sid = (int) $report['school']['id'];
        $term = (int) $report['term_id'];
        $student = (int) $report['student']['id'];
        $hash = self::snapshot($report);
        $token = '';

        DB::transaction(static function () use ($sid, $term, $student, $hash, &$token): void {
            // Serialize token issuance for this report, including simultaneous first-time print requests.
            $lockSql = 'SELECT id FROM result_summaries WHERE school_id = ? AND term_id = ? AND student_id = ?';
            if (DB::isMysql()) $lockSql .= ' FOR UPDATE';
            DB::one($lockSql, [$sid, $term, $student]);

            $existing = DB::one(
                'SELECT token, snapshot_hash FROM result_verifications
                 WHERE school_id = ? AND term_id = ? AND student_id = ? AND revoked_at IS NULL
                 ORDER BY id DESC LIMIT 1',
                [$sid, $term, $student]
            );
            if ($existing !== null && hash_equals((string) $existing['snapshot_hash'], $hash)) {
                $token = (string) $existing['token'];
                return;
            }

            DB::run(
                'UPDATE result_verifications SET revoked_at = NOW()
                 WHERE school_id = ? AND term_id = ? AND student_id = ? AND revoked_at IS NULL',
                [$sid, $term, $student]
            );
            $token = bin2hex(random_bytes(16));
            DB::insert(
                'INSERT INTO result_verifications (token, school_id, term_id, student_id, snapshot_hash) VALUES (?,?,?,?,?)',
                [$token, $sid, $term, $student, $hash]
            );
        });
        return ['token' => $token, 'hash' => $hash];
    }

    public static function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        return DB::one(
            'SELECT rv.*, s.name AS school_name FROM result_verifications rv
             JOIN schools s ON s.id = rv.school_id
             WHERE rv.token = ? LIMIT 1',
            [$token]
        );
    }
}
