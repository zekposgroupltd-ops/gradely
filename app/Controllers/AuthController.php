<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, Http, RateLimiter, Session, View};

final class AuthController
{
    private const WINDOW = 900; // 15 minutes

    public function showLogin(): void
    {
        if (Auth::check()) {
            Http::redirect(Auth::home());
        }
        View::render('auth/login', ['title' => 'Sign in'], 'auth');
    }

    public function login(): void
    {
        $email = strtolower(Http::input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $ip = Http::ip();
        $ipKey = 'login:ip:' . $ip;
        $emailKey = 'login:email:' . $email;

        $fail = static function (string $message) use ($email): never {
            Session::flash('error', $message);
            Session::flashOld(['email' => $email]);
            Http::redirect('/login');
        };

        if ($email === '' || $password === '') {
            $fail('Enter your email address and password.');
        }
        if (RateLimiter::tooMany($ipKey, 30, self::WINDOW) || RateLimiter::tooMany($emailKey, 5, self::WINDOW)) {
            AuditLog::record('login.throttled', 'user', null, ['email' => $email]);
            $fail('Too many sign-in attempts. Please wait 15 minutes and try again.');
        }

        $result = Auth::attempt($email, $password);
        if (!$result['ok']) {
            RateLimiter::hit($ipKey, self::WINDOW);
            RateLimiter::hit($emailKey, self::WINDOW);
            AuditLog::record('login.failed', 'user', null, ['email' => $email]);
            $fail($result['error'] ?? 'Invalid email or password.');
        }

        $user = $result['user'];
        RateLimiter::clear($emailKey);
        Auth::login($user);
        AuditLog::record('login.success', 'user', $user['id'], [], $user['school_id'] !== null ? (int) $user['school_id'] : null, (int) $user['id']);
        Http::redirect(Auth::home());
    }

    public function logout(): void
    {
        AuditLog::record('logout', 'user', Auth::user()['id'] ?? null);
        Auth::logout();
        Session::flash('success', 'You have been signed out.');
        Http::redirect('/login');
    }
}
