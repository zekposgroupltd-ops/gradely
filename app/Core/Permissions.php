<?php
declare(strict_types=1);

namespace App\Core;

/** Permissions a school administrator can grant to a teacher. School administrators implicitly hold all of them. */
final class Permissions
{
    public const LIST = [
        'students.manage' => 'Manage students (add, edit, import)',
        'scores.enter' => 'Enter and edit scores (for assigned classes and subjects)',
        'results.view' => 'View results, report cards and analytics',
        'results.approve' => 'Approve results',
        'results.publish' => 'Publish and unpublish results',
        'cards.manage' => 'Generate and manage scratch cards',
    ];

    /** @param array<int,string> $submitted */
    public static function clean(array $submitted): array
    {
        return array_values(array_intersect(array_keys(self::LIST), array_map('strval', $submitted)));
    }
}
