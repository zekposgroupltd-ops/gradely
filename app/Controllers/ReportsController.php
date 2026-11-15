<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, View};
use App\Services\ReportService;

/** Back-office report cards: one student, or a whole class for bulk printing / PDF. */
final class ReportsController extends BaseController
{
    public function student(): void
    {
        $term = $this->owned('terms', (int) Http::input('term_id'));
        $student = $this->owned('students', (int) Http::input('student_id'));
        $r = ReportService::build($this->sid(), (int) $term['id'], (int) $student['id'], false);
        if ($r === null) {
            Http::abort(404, 'No computed result exists for this student in that term yet.');
        }
        AuditLog::record('report.viewed', 'student', $student['id'], ['term' => $term['name']]);
        View::render('report/page', ['title' => $student['last_name'] . ' ' . $student['first_name'] . ' - report card', 'reports' => [$r], 'portal' => false, 'back' => '/school/results?term_id=' . $term['id'] . '&class_id=' . $r['student']['class_id']], 'report');
    }

    public function classBulk(): void
    {
        $term = $this->owned('terms', (int) Http::input('term_id'));
        $class = $this->owned('classes', (int) Http::input('class_id'));
        $sid = $this->sid();
        $reports = [];
        foreach (DB::all(
            'SELECT rs.student_id FROM result_summaries rs JOIN students st ON st.id = rs.student_id AND st.school_id = rs.school_id
             WHERE rs.school_id = ? AND rs.term_id = ? AND rs.class_id = ? ORDER BY st.last_name, st.first_name',
            [$sid, $term['id'], $class['id']]
        ) as $row) {
            $r = ReportService::build($sid, (int) $term['id'], (int) $row['student_id'], false);
            if ($r !== null) {
                $reports[] = $r;
            }
        }
        if (!$reports) {
            Http::abort(404, 'No computed results exist for this class and term yet.');
        }
        AuditLog::record('report.bulk_viewed', 'class', $class['id'], ['term' => $term['name'], 'count' => count($reports)]);
        View::render('report/page', ['title' => $class['name'] . ' - report cards', 'reports' => $reports, 'portal' => false, 'back' => '/school/results?term_id=' . $term['id'] . '&class_id=' . $class['id']], 'report');
    }
}
