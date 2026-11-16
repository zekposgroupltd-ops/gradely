<?php
declare(strict_types=1);

namespace App\Core;

/** Fixed-window, database-backed limiter (no Redis/APCu needed on shared hosting). */
final class RateLimiter
{
    private static function k(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        $row = DB::one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [self::k($key)]);
        return $row !== null
            && (int) $row['window_start'] > time() - $windowSeconds
            && (int) $row['hits'] >= $max;
    }

    public static function hit(string $key, int $windowSeconds): void
    {
        $k = self::k($key);
        $now = time();
        $row = DB::one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [$k]);
        if ($row === null) {
            try {
                DB::run('INSERT INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)', [$k, $now]);
            } catch (\PDOException) {
                // A concurrent request created the row first; count this hit on it.
                DB::run('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$k]);
            }
            return;
        }
        if ((int) $row['window_start'] <= $now - $windowSeconds) {
            DB::run('UPDATE rate_limits SET hits = 1, window_start = ? WHERE rkey = ?', [$now, $k]);
        } else {
            DB::run('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$k]);
        }
    }

    public static function clear(string $key): void
    {
        DB::run('DELETE FROM rate_limits WHERE rkey = ?', [self::k($key)]);
    }
}
