<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Auth, DB};

/** Creates a school with everything it needs to start: settings, default grading, default components, first admin. */
final class SchoolProvisioner
{
    /** Sensible defaults a school can edit later (Phase 3 UI). */
    private const GRADES = [
        ['A', 70, 100, 'Excellent'],
        ['B', 60, 69.99, 'Very Good'],
        ['C', 50, 59.99, 'Good'],
        ['D', 45, 49.99, 'Fair'],
        ['E', 40, 44.99, 'Pass'],
        ['F', 0, 39.99, 'Fail'],
    ];
    private const COMPONENTS = [['1st Test', 20, 0], ['2nd Test', 20, 0], ['Exam', 60, 1]];

    public static function slugFor(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $base = substr($base !== '' ? $base : 'school', 0, 60);
        $slug = $base;
        for ($i = 2; DB::val('SELECT COUNT(*) FROM schools WHERE slug = ?', [$slug]) > 0; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    /**
     * @param array{name:string,email:?string,phone:?string,address:?string} $school
     * @param array{name:string,email:string,password:string} $admin
     * @return int new school id
     */
    public static function create(array $school, array $admin): int
    {
        return (int) DB::transaction(static function () use ($school, $admin): int {
            $sid = DB::insert(
                'INSERT INTO schools (name, slug, email, phone, address) VALUES (?,?,?,?,?)',
                [$school['name'], self::slugFor($school['name']), $school['email'], $school['phone'], $school['address']]
            );
            DB::insert('INSERT INTO school_settings (school_id) VALUES (?)', [$sid]);
            foreach (self::GRADES as $i => [$g, $min, $max, $remark]) {
                DB::insert(
                    'INSERT INTO grade_boundaries (school_id, grade, min_score, max_score, remark, sort_order) VALUES (?,?,?,?,?,?)',
                    [$sid, $g, $min, $max, $remark, $i]
                );
            }
            foreach (self::COMPONENTS as $i => [$name, $max, $isExam]) {
                DB::insert(
                    'INSERT INTO assessment_components (school_id, name, max_score, sort_order, is_exam) VALUES (?,?,?,?,?)',
                    [$sid, $name, $max, $i, $isExam]
                );
            }
            DB::insert(
                "INSERT INTO users (school_id, name, email, password_hash, role) VALUES (?,?,?,?, 'school_admin')",
                [$sid, $admin['name'], $admin['email'], Auth::hash($admin['password'])]
            );
            return $sid;
        });
    }
}
