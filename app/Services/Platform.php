<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Platform-wide settings managed by the Super Administrator (support contact, announcement banner). */
final class Platform
{
    public const KEYS = ['support_email', 'support_phone', 'announcement'];
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = array_fill_keys(self::KEYS, '');
            try {
                foreach (DB::all('SELECT skey, svalue FROM platform_settings') as $r) {
                    if (in_array($r['skey'], self::KEYS, true)) {
                        self::$cache[$r['skey']] = (string) $r['svalue'];
                    }
                }
            } catch (\PDOException) {
                // before the schema is imported
            }
        }
        return self::$cache;
    }

    public static function get(string $key): string
    {
        return self::all()[$key] ?? '';
    }

    public static function set(string $key, string $value): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException('Unknown platform setting.');
        }
        DB::transaction(static function () use ($key, $value): void {
            DB::run('DELETE FROM platform_settings WHERE skey = ?', [$key]);
            DB::run('INSERT INTO platform_settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
        });
        self::$cache = null;
    }
}
