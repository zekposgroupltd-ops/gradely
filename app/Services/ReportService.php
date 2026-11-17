<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Builds the data behind a report card. The school's display setting (grades / positions / both) is
 * enforced HERE by removing the disabled values, so no view, PDF or portal page can ever show them.
 */
final class ReportService
{
    /** @return ?array<string,mixed> null when the student has no (published) result for the term */
    public static function build(int $sid, int $termId, int $studentId, bool $publishedOnly): ?array
    {
        $sum = DB::one(
            'SELECT * FROM result_summaries WHERE school_id = ? AND term_id = ? AND student_id = ?' . ($publishedOnly ? " AND status = 'published'" : ''),
            [$sid, $termId, $studentId]
        );
        if ($sum === null) {
            return null;
        }
        $school = DB::one('SELECT id, name, email, phone, address, website, logo_path FROM schools WHERE id = ?', [$sid]);
        $cfg = DB::one('SELECT * FROM school_settings WHERE school_id = ?', [$sid]);
        $student = DB::one(
            'SELECT st.*, c.name AS class_name, a.name AS arm_name FROM students st
             JOIN classes c ON c.id = ? AND c.school_id = st.school_id
             LEFT JOIN class_arms a ON a.id = ? AND a.school_id = st.school_id
             WHERE st.id = ? AND st.school_id = ?',
            [$sum['class_id'], $sum['arm_id'], $studentId, $sid]
        );
        $term = DB::one(
            'SELECT t.name AS term_name, t.next_term_begins, s.name AS session_name FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.id = ? AND t.school_id = ?',
            [$termId, $sid]
        );
        if ($school === null || $cfg === null || $student === null || $term === null) {
            return null;
        }
        $components = DB::all('SELECT id, name, max_score FROM assessment_components WHERE school_id = ? ORDER BY sort_order, id', [$sid]);
        $maxTotal = array_sum(array_map(static fn($c) => (float) $c['max_score'], $components));

        $scores = [];
        foreach (DB::all('SELECT subject_id, component_id, score FROM scores WHERE school_id = ? AND term_id = ? AND student_id = ?', [$sid, $termId, $studentId]) as $r) {
            $scores[$r['subject_id']][$r['component_id']] = (float) $r['score'];
        }
        $results = [];
        foreach (DB::all('SELECT subject_id, total, grade, remark, subject_position FROM subject_results WHERE school_id = ? AND term_id = ? AND student_id = ?', [$sid, $termId, $studentId]) as $r) {
            $results[$r['subject_id']] = $r;
        }
        $classAvg = [];
        foreach (DB::all('SELECT subject_id, AVG(total) AS a FROM subject_results WHERE school_id = ? AND term_id = ? AND class_id = ? GROUP BY subject_id', [$sid, $termId, $sum['class_id']]) as $r) {
            $classAvg[$r['subject_id']] = round((float) $r['a'], 1);
        }

        $showGrades = in_array($cfg['display_mode'], ['grades', 'both'], true);
        $showPositions = in_array($cfg['display_mode'], ['positions', 'both'], true);

        $rows = [];
        foreach (DB::all(
            'SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id AND s.school_id = cs.school_id WHERE cs.school_id = ? AND cs.class_id = ? ORDER BY s.name',
            [$sid, $sum['class_id']]
        ) as $sub) {
            $res = $results[$sub['id']] ?? null;
            $row = [
                'subject' => $sub['name'],
                'scores' => array_map(static fn($c) => $scores[$sub['id']][$c['id']] ?? null, $components),
                'total' => $res === null ? null : (float) $res['total'],
                'class_average' => $classAvg[$sub['id']] ?? null,
                'grade' => null, 'remark' => null, 'position' => null,
            ];
            if ($res !== null && $showGrades) {
                $row['grade'] = $res['grade'];
                $row['remark'] = $res['remark'];
            }
            if ($res !== null && $showPositions) {
                $row['position'] = $res['subject_position'] === null ? null : (int) $res['subject_position'];
            }
            $rows[] = $row;
        }

        $avg = (float) $sum['average'];
        $overallGrade = null;
        $key = [];
        if ($showGrades) {
            $bounds = DB::all('SELECT grade, min_score, max_score, remark FROM grade_boundaries WHERE school_id = ? ORDER BY min_score DESC', [$sid]);
            [$overallGrade] = ResultEngine::grade($avg, $bounds);
            $key = $bounds;
        }

        $report = [
            'school' => $school, 'cfg' => $cfg, 'student' => $student, 'term' => $term, 'term_id' => $termId,
            'components' => $components, 'rows' => $rows, 'max_total' => $maxTotal,
            'summary' => [
                'subjects' => (int) $sum['subjects_count'],
                'total' => (float) $sum['total'],
                'out_of' => $maxTotal * (int) $sum['subjects_count'],
                'average' => $avg,
                'grade' => $overallGrade,
                'position' => $showPositions && $sum['position'] !== null ? (int) $sum['position'] : null,
                'class_size' => $showPositions ? (int) $sum['class_size'] : null,
            ],
            'show_grades' => $showGrades, 'show_positions' => $showPositions,
            'grade_key' => $key,
            'teacher_comment' => $sum['teacher_comment'], 'principal_comment' => $sum['principal_comment'],
            'status' => $sum['status'],
        ];
        if ($sum['status'] === 'published') {
            $verification = ResultVerification::issue($report);
            $report['verification'] = [
                'token' => $verification['token'],
                'url' => url('/verify/' . $verification['token']),
                'barcode_svg' => Barcode128::svg(url('/verify/' . $verification['token'])),
            ];
        }
        return $report;
    }
}
