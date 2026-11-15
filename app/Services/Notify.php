<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Notify
{
    /** Notifies every active school administrator of a school. */
    public static function schoolAdmins(int $schoolId, string $title, ?string $body = null): void
    {
        foreach (DB::all("SELECT id FROM users WHERE school_id = ? AND role = 'school_admin' AND status = 'active'", [$schoolId]) as $u) {
            self::user($schoolId, (int) $u['id'], $title, $body);
        }
    }

    public static function user(?int $schoolId, int $userId, string $title, ?string $body = null): void
    {
        DB::run(
            'INSERT INTO notifications (school_id, user_id, title, body) VALUES (?,?,?,?)',
            [$schoolId, $userId, mb_substr($title, 0, 150), $body === null ? null : mb_substr($body, 0, 500)]
        );
    }
}
