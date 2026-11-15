<?php
declare(strict_types=1);

namespace App\Core;

/** Sidebar entries per user. `also` lists extra path prefixes that keep an entry highlighted. */
final class Nav
{
    /** @return array<int,array{label:string,path:string,icon:string,also:array<int,string>}> */
    public static function items(?array $user): array
    {
        if ($user === null) {
            return [];
        }
        $i = static fn(string $label, string $path, string $icon, array $also = []): array => ['label' => $label, 'path' => $path, 'icon' => $icon, 'also' => $also];

        if ($user['role'] === 'super_admin') {
            return [
                $i('Overview', '/super', 'dashboard'),
                $i('Schools', '/super/schools', 'school'),
                $i('Cards', '/super/cards', 'card'),
                $i('Activity', '/super/logs', 'shield'),
                $i('Platform', '/super/admins', 'settings', ['/super/notifications', '/super/settings']),
            ];
        }

        $items = [$i('Dashboard', '/school', 'dashboard')];
        if (Auth::can('students.manage')) {
            $items[] = $i('Students', '/school/students', 'users');
        }
        if (Auth::can('scores.enter')) {
            $items[] = $i('Scores', '/school/scores', 'edit');
        }
        if (Auth::can('results.view')) {
            $items[] = $i('Results', '/school/results', 'book', ['/school/reports', '/school/archive']);
            $items[] = $i('Insights', '/school/analytics', 'chart');
        }
        if (Auth::can('cards.manage')) {
            $items[] = $i('Cards', '/school/cards', 'card');
        }
        if ($user['role'] === 'school_admin') {
            $items[] = $i('Setup', '/school/setup', 'settings', ['/school/classes', '/school/subjects', '/school/academics', '/school/teachers', '/school/profile', '/school/settings']);
        }
        return $items;
    }
}
