<?php
declare(strict_types=1);

namespace App\Core;

/** Minimal .env loader. Real environment variables take priority over the file. */
final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                $v = substr($v, 1, -1);
            }
            self::$vars[$k] = $v;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
            return self::$vars[$key];
        }
        // Hosting platforms usually inject ONE connection string instead of separate DB_* settings.
        if (str_starts_with($key, 'DB_') && $key !== 'DB_SQLITE_PATH' && ($v = self::fromDatabaseUrl($key)) !== null) {
            return $v;
        }
        return $default ?? ($real !== false ? $real : null);
    }

    /** Reads DATABASE_URL / MYSQL_URL (mysql://user:pass@host:3306/name) for the matching DB_* key. */
    public static function fromDatabaseUrl(string $key, ?string $url = null): ?string
    {
        $url ??= (string) (getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: (self::$vars['DATABASE_URL'] ?? self::$vars['MYSQL_URL'] ?? ''));
        if ($url === '') {
            return null;
        }
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme']) || !in_array(strtolower($p['scheme']), ['mysql', 'mysqli', 'mariadb'], true)) {
            return null;
        }
        return match ($key) {
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => $p['host'] ?? null,
            'DB_PORT' => isset($p['port']) ? (string) $p['port'] : null,
            'DB_NAME' => isset($p['path']) && ltrim($p['path'], '/') !== '' ? rawurldecode(ltrim($p['path'], '/')) : null,
            'DB_USER' => isset($p['user']) ? rawurldecode($p['user']) : null,
            'DB_PASS' => isset($p['pass']) ? rawurldecode($p['pass']) : null,
            default => null,
        };
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Required secret; refuses to run with a missing or weak value. */
    public static function secret(): string
    {
        $s = (string) self::get('APP_SECRET', '');
        if (strlen($s) < 32 || str_starts_with($s, 'change-me')) {
            throw new \RuntimeException('APP_SECRET is missing or too weak. Set a random string of 32+ characters in .env.');
        }
        return $s;
    }
}
