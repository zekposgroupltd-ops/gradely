<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, View};
use App\Services\ReportService;

/** Back-office report cards: one student, or a whole class for bulk printing / PDF. */
final class ReportsController extends BaseController
{
    /** A class report is printable only when the school has generated a purchased PIN for every active student in scope. */
    private function requirePinCoverage(int $termId, int $classId): void
    {
        $sid = $this->sid();
        $classRequired = (int) DB::val('SELECT COUNT(DISTINCT student_id) FROM result_summaries WHERE school_id = ? AND term_id = ? AND class_id = ?', [$sid, $termId, $classId]);
        if ($classRequired === 0) { $classRequired = (int) DB::val("SELECT COUNT(*) FROM students WHERE school_id = ? AND class_id = ? AND status = 'active'", [$sid, $classId]); }
        $schoolRequired = (int) DB::val('SELECT COUNT(DISTINCT student_id) FROM result_summaries WHERE school_id = ? AND term_id = ?', [$sid, $termId]);
        if ($schoolRequired === 0) { $schoolRequired = (int) DB::val("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'", [$sid]); }
        // Class-specific allocations cover only that class. A school-wide allocation is valid only
        // when its PIN count covers the entire active school roll; it cannot be reused per class.
        $classIssued = (int) DB::val("SELECT COUNT(*) FROM scratch_cards c JOIN scratch_card_batches b ON b.id = c.batch_id AND b.school_id = c.school_id JOIN pin_requests pr ON pr.id = b.request_id AND pr.school_id = b.school_id WHERE c.school_id = ? AND c.term_id = ? AND b.class_id = ? AND pr.status = 'fulfilled' AND c.status NOT IN ('revoked','expired') AND (c.expires_at IS NULL OR c.expires_at > NOW())", [$sid, $termId, $classId]);
        $schoolIssued = (int) DB::val("SELECT COUNT(*) FROM scratch_cards c JOIN scratch_card_batches b ON b.id = c.batch_id AND b.school_id = c.school_id JOIN pin_requests pr ON pr.id = b.request_id AND pr.school_id = b.school_id WHERE c.school_id = ? AND c.term_id = ? AND b.class_id IS NULL AND pr.status = 'fulfilled' AND c.status NOT IN ('revoked','expired') AND (c.expires_at IS NULL OR c.expires_at > NOW())", [$sid, $termId]);
        $classCovered = $classRequired > 0 && $classIssued >= $classRequired;
        $schoolCovered = $schoolRequired > 0 && $schoolIssued >= $schoolRequired;
        if (!$classCovered && !$schoolCovered) {
            Http::abort(403, 'PDF printing is locked. Buy at least ' . $classRequired . ' valid PIN(s) for this class, or ' . $schoolRequired . ' PIN(s) for the entire school. Current allocations: ' . $classIssued . ' class PIN(s), ' . $schoolIssued . ' school-wide PIN(s). Submit a PIN request under Scratch Cards.');
        }
    }

    public function student(): void
    {
        $term = $this->owned('terms', (int) Http::input('term_id'));
        $student = $this->owned('students', (int) Http::input('student_id'));
        $resultClassId = (int) DB::val('SELECT class_id FROM result_summaries WHERE school_id = ? AND term_id = ? AND student_id = ?', [$this->sid(), $term['id'], $student['id']]);
        if ($resultClassId <= 0) { Http::abort(404, 'No computed result exists for this student in that term yet.'); }
        $this->requirePinCoverage((int) $term['id'], $resultClassId);
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
        $this->requirePinCoverage((int) $term['id'], (int) $class['id']);
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
