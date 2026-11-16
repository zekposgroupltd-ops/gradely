<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, DB, View};

final class SchoolController
{
    public function dashboard(): void
    {
        $sid = Auth::schoolId(); // from the session only; every query below is scoped by it

        $school = DB::one('SELECT name, email, phone, address, status FROM schools WHERE id = ?', [$sid]) ?? [];
        $count = static fn(string $sql): int => (int) DB::val($sql, [$sid]);

        $counts = [
            'students' => $count("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'"),
            'classes' => $count('SELECT COUNT(*) FROM classes WHERE school_id = ?'),
            'subjects' => $count('SELECT COUNT(*) FROM subjects WHERE school_id = ?'),
            'components' => $count('SELECT COUNT(*) FROM assessment_components WHERE school_id = ?'),
        ];

        $current = DB::one(
            'SELECT s.name AS session_name, t.name AS term_name
             FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.school_id = ? AND t.is_current = 1 LIMIT 1',
            [$sid]
        );

        // Guided setup checklist for new schools.
        $cardCount = $count('SELECT COUNT(*) FROM scratch_cards WHERE school_id = ?');
        $checklist = [
            ['Add a session and a term', (int) DB::val('SELECT COUNT(*) FROM terms WHERE school_id = ?', [$sid]) > 0, '/school/academics'],
            ['Create classes', $counts['classes'] > 0, '/school/classes'],
            ['Create subjects and assign them to classes', $count('SELECT COUNT(*) FROM class_subjects WHERE school_id = ?') > 0, '/school/subjects'],
            ['Register students', $counts['students'] > 0, '/school/students'],
            ['Enter scores', $count('SELECT COUNT(*) FROM scores WHERE school_id = ?') > 0, '/school/scores'],
            ['Publish results', $count("SELECT COUNT(*) FROM result_summaries WHERE school_id = ? AND status = 'published'") > 0, '/school/results'],
            ['Generate scratch cards', $cardCount > 0, '/school/cards'],
        ];

        View::render('school/dashboard', [
            'title' => 'Dashboard',
            'school' => $school,
            'counts' => $counts,
            'current' => $current,
            'checklist' => $checklist,
            'announcement' => \App\Services\Platform::get('announcement'),
        ]);
    }

    public function setup(): void
    {
        View::render('school/setup', ['title' => 'School setup']);
    }
}
