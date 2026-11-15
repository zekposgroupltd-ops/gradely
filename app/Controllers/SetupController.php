<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Env, Http, RateLimiter, Session, View};

/**
 * One-time creation of the first Super Administrator, for hosts without SSH.
 * Works only while no super admin exists AND SETUP_TOKEN is set in .env.
 */
final class SetupController
{
    /** @return 'ready'|'done'|'no_db'|'no_token' */
    private function state(): string
    {
        try {
            if ((int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'super_admin'") > 0) {
                return 'done';
            }
        } catch (\PDOException) {
            return 'no_db';
        }
        return (string) Env::get('SETUP_TOKEN', '') === '' ? 'no_token' : 'ready';
    }

    public function show(): void
    {
        $state = $this->state();
        if ($state === 'done') {
            Http::abort(404);
        }
        View::render('auth/setup', ['title' => 'First-time setup', 'state' => $state], 'auth');
    }

    public function store(): void
    {
        $state = $this->state();
        if ($state === 'done') {
            Http::abort(404);
        }
        if ($state !== 'ready') {
            Http::redirect('/setup');
        }

        $ipKey = 'setup:ip:' . Http::ip();
        if (RateLimiter::tooMany($ipKey, 5, 900)) {
            Http::abort(429, 'Too many attempts. Please wait 15 minutes.');
        }

        $name = Http::input('name');
        $email = strtolower(Http::input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $token = Http::input('setup_token');

        $error = null;
        if (!hash_equals((string) Env::get('SETUP_TOKEN', ''), $token)) {
            RateLimiter::hit($ipKey, 900);
            $error = 'The setup token is not correct.';
        } elseif ($name === '' || mb_strlen($name) > 150) {
            $error = 'Enter a name (up to 150 characters).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < Auth::MIN_PASSWORD) {
            $error = 'The password must be at least ' . Auth::MIN_PASSWORD . ' characters.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'The two passwords do not match.';
        }
        if ($error !== null) {
            Session::flash('error', $error);
            Session::flashOld(['name' => $name, 'email' => $email]);
            Http::redirect('/setup');
        }

        $id = DB::insert(
            "INSERT INTO users (school_id, name, email, password_hash, role, status) VALUES (NULL, ?, ?, ?, 'super_admin', 'active')",
            [$name, $email, Auth::hash($password)]
        );
        AuditLog::record('setup.super_admin_created', 'user', $id, [], null, $id);
        Session::flash('success', 'Super Administrator created. Remove SETUP_TOKEN from .env, then sign in.');
        Http::redirect('/login');
    }
}
