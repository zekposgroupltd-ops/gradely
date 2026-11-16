<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{DB, View};

final class SuperController
{
    public function dashboard(): void
    {
        $schools = ['active' => 0, 'suspended' => 0, 'inactive' => 0];
        foreach (DB::all('SELECT status, COUNT(*) AS c FROM schools GROUP BY status') as $r) {
            $schools[$r['status']] = (int) $r['c'];
        }

        $cards = DB::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN use_count > 0 THEN 1 ELSE 0 END), 0) AS used,
                    COALESCE(SUM(CASE WHEN status = 'exhausted' THEN 1 ELSE 0 END), 0) AS exhausted
             FROM scratch_cards"
        ) ?? ['total' => 0, 'used' => 0, 'exhausted' => 0];

        $recent = DB::all(
            'SELECT a.action, a.created_at, a.ip, u.name AS user_name, s.name AS school_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN schools s ON s.id = a.school_id
             ORDER BY a.id DESC LIMIT 10'
        );

        View::render('super/dashboard', [
            'title' => 'Platform overview',
            'schools' => $schools,
            'staffCount' => (int) DB::val("SELECT COUNT(*) FROM users WHERE role <> 'super_admin'"),
            'cards' => array_map('intval', $cards),
            'recent' => $recent,
        ]);
    }
}
