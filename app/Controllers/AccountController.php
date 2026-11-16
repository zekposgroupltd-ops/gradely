<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, RateLimiter, View};

final class AccountController extends BaseController
{
    public function password(): void
    {
        View::render('account/password', ['title' => 'My account']);
    }

    public function updatePassword(): void
    {
        $u = Auth::user();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $key = 'pwchange:' . $u['id'];
        if (RateLimiter::tooMany($key, 5, 900)) {
            $this->fail('Too many attempts. Please wait 15 minutes.', '/account/password');
        }
        $hash = (string) DB::val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
        if (!password_verify($current, $hash)) {
            RateLimiter::hit($key, 900);
            $this->fail('Your current password is not correct.', '/account/password');
        }
        if (strlen($new) < Auth::MIN_PASSWORD) {
            $this->fail('The new password must be at least ' . Auth::MIN_PASSWORD . ' characters.', '/account/password');
        }
        if (!hash_equals($new, $confirm)) {
            $this->fail('The new passwords do not match.', '/account/password');
        }
        DB::run('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $u['id']]);
        RateLimiter::clear($key);
        \App\Core\Session::regenerate();
        AuditLog::record('user.password_changed', 'user', $u['id']);
        $this->ok('Your password has been changed.', '/account/password');
    }
}
