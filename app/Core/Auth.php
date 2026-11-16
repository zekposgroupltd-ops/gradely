<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public const MIN_PASSWORD = 10;

    public static function hash(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_hash($password, $algo);
    }

    /**
     * Verifies credentials only (no session changes).
     * @return array{ok:bool,error:?string,user:?array}
     */
    public static function attempt(string $email, string $password): array
    {
        $row = DB::one(
            'SELECT u.*, s.status AS school_status FROM users u LEFT JOIN schools s ON s.id = u.school_id WHERE u.email = ?',
            [$email]
        );
        if ($row === null) {
            password_hash($password, PASSWORD_DEFAULT); // equalise timing with the found-user path
            return ['ok' => false, 'error' => 'Invalid email or password.', 'user' => null];
        }
        if (!password_verify($password, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid email or password.', 'user' => null];
        }
        // Password is correct from here on, so it is safe to be specific.
        if ($row['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account has been disabled. Please contact your school administrator.', 'user' => null];
        }
        if ($row['role'] !== 'super_admin' && $row['school_status'] !== 'active') {
            return ['ok' => false, 'error' => 'This school account is not active. Please contact GRADELY support.', 'user' => null];
        }
        if (password_needs_rehash($row['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hash($password), $row['id']]);
        }
        return ['ok' => true, 'error' => null, 'user' => $row];
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        $_SESSION['uid'] = (int) $user['id'];
        DB::run('UPDATE users SET last_login_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $user['id']]);
        self::$user = null;
        self::$resolved = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$resolved = true;
    }

    /**
     * Current user, re-read from the database on every request so that disabling a
     * user or suspending a school takes effect immediately.
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $uid = $_SESSION['uid'] ?? null;
        if (!is_int($uid)) {
            return self::$user = null;
        }
        $u = DB::one(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.school_id, s.name AS school_name, s.status AS school_status, s.logo_path
             FROM users u LEFT JOIN schools s ON s.id = u.school_id WHERE u.id = ?',
            [$uid]
        );
        if ($u === null || $u['status'] !== 'active'
            || ($u['role'] !== 'super_admin' && $u['school_status'] !== 'active')) {
            unset($_SESSION['uid']);
            return self::$user = null;
        }
        $u['perms'] = $u['role'] === 'teacher'
            ? array_column(DB::all('SELECT permission FROM user_permissions WHERE user_id = ?', [$u['id']]), 'permission')
            : [];
        return self::$user = $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        if ($u['role'] === 'super_admin' || $u['role'] === 'school_admin') {
            return true;
        }
        return in_array($permission, $u['perms'], true);
    }

    /**
     * The tenant for the current request. This ALWAYS comes from the authenticated
     * session, never from a URL or form field.
     */
    public static function schoolId(): int
    {
        $id = self::user()['school_id'] ?? null;
        if ($id === null) {
            Http::abort(403, 'No school is associated with this account.');
        }
        return (int) $id;
    }

    public static function home(): string
    {
        return self::role() === 'super_admin' ? '/super' : '/school';
    }
}
