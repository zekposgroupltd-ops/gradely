<?php
declare(strict_types=1);

namespace App\Core;

/** Route guards. Each factory returns a closure that aborts/redirects when access is not allowed. */
final class Middleware
{
    public static function auth(): \Closure
    {
        return static function (): void {
            if (!Auth::check()) {
                Session::flash('error', 'Please sign in to continue.');
                Http::redirect('/login');
            }
        };
    }

    public static function role(string ...$roles): \Closure
    {
        return static function () use ($roles): void {
            if (!in_array(Auth::role(), $roles, true)) {
                Http::abort(403, 'You do not have permission to view this page.');
            }
        };
    }

    /** School-scoped staff (school_admin or teacher) of an active school. */
    public static function school(): \Closure
    {
        return static function (): void {
            $u = Auth::user();
            if ($u === null || !in_array($u['role'], ['school_admin', 'teacher'], true) || empty($u['school_id'])) {
                Http::abort(403, 'You do not have permission to view this page.');
            }
        };
    }

    public static function can(string $permission): \Closure
    {
        return static function () use ($permission): void {
            if (!Auth::can($permission)) {
                Http::abort(403, 'You do not have permission to do that.');
            }
        };
    }
}
