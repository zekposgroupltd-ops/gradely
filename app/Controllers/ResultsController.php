<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Http, View};
use App\Services\{Notify, ResultEngine};

/** Result processing workflow (compute → approve → publish), comments, exports, analytics and archive. */
final class ResultsController extends BaseController
{
    private function terms(): array
    {
        return DB::all(
            'SELECT t.id, t.name, t.is_current, s.name AS session_name FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.school_id = ? ORDER BY s.name DESC, t.id DESC',
            [$this->sid()]
        );
    }

    private function classes(): array
    {
        return DB::all('SELECT id, name FROM classes WHERE school_id = ? ORDER BY level_order, name', [$this->sid()]);
    }

    private function currentTermId(array $terms): int
    {
        $given = (int) Http::input('term_id');
        if ($given > 0) {
            return $given;
        }
        foreach ($terms as $t) {
            if ((int) $t['is_current'] === 1) {
                return (int) $t['id'];
            }
        }
        return 0;
    }

    private function back(int $termId, int $classId): string
    {
        return "/school/results?term_id=$termId&class_id=$classId";
    }

    public function index(): void
    {
        $sid = $this->sid();
        $terms = $this->terms();
        $termId = $this->currentTermId($terms);
        $classId = (int) Http::input('class_id');
        $classes = $this->classes();

        $view = null;
        if ($termId > 0 && $classId > 0
            && DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) > 0
            && DB::val('SELECT COUNT(*) FROM classes WHERE id = ? AND school_id = ?', [$classId, $sid]) > 0) {
            $view = [
                'status' => ResultEngine::classStatus($sid, $termId, $classId),
                'completeness' => ResultEngine::completeness($sid, $termId, $classId),
                'rows' => DB::all(
                    'SELECT rs.*, st.first_name, st.last_name, st.admission_no FROM result_summaries rs
                     JOIN students st ON st.id = rs.student_id AND st.school_id = rs.school_id
                     WHERE rs.school_id = ? AND rs.term_id = ? AND rs.class_id = ?
                     ORDER BY CASE WHEN rs.position IS NULL THEN 1 ELSE 0 END, rs.position, st.last_name',
                    [$sid, $termId, $classId]
                ),
                'cfg' => DB::one('SELECT display_mode, position_by, tie_mode FROM school_settings WHERE school_id = ?', [$sid]),
            ];
        }
        View::render('school/results', ['title' => 'Results', 'terms' => $terms, 'classes' => $classes, 'termId' => $termId, 'classId' => $classId, 'view' => $view]);
    }

    /** @return array{0:array,1:array} owned term and class from POST */
    private function target(): array
    {
        return [$this->owned('terms', (int) Http::input('term_id')), $this->owned('classes', (int) Http::input('class_id'))];
    }

    private function record(int $termId, int $classId, string $action): void
    {
        DB::insert(
            'INSERT INTO result_publications (school_id, term_id, class_id, action, acted_by) VALUES (?,?,?,?,?)',
            [$this->sid(), $termId, $classId, $action, Auth::user()['id']]
        );
    }

    public function compute(): void
    {
        [$term, $class] = $this->target();
        $back = $this->back((int) $term['id'], (int) $class['id']);
        if (!Auth::can('scores.enter') && !Auth::can('results.approve')) {
            Http::abort(403, 'You do not have permission to do that.');
        }
        try {
            $r = ResultEngine::compute($this->sid(), (int) $term['id'], (int) $class['id']);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), $back);
        }
        AuditLog::record('results.computed', 'class', $class['id'], $r + ['term' => $term['name']]);
        $this->ok("Results computed for {$class['name']}: {$r['computed']} of {$r['students']} students ranked.", $back);
    }

    public function approve(): void
    {
        if (!Auth::can('results.approve')) {
            Http::abort(403, 'You do not have permission to approve results.');
        }
        [$term, $class] = $this->target();
        $sid = $this->sid();
        $back = $this->back((int) $term['id'], (int) $class['id']);
        if (ResultEngine::classStatus($sid, (int) $term['id'], (int) $class['id']) === 'approved') {
            $this->fail('These results are already approved.', $back);
        }
        try {
            $r = ResultEngine::compute($sid, (int) $term['id'], (int) $class['id']); // always approve fresh numbers
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), $back);
        }
        $c = ResultEngine::completeness($sid, (int) $term['id'], (int) $class['id']);
        if ($c['missing']) {
            $this->fail(count($c['missing']) . ' score set(s) are still missing, so the results cannot be approved. Complete them first (see the list below).', $back);
        }
        if ($r['computed'] === 0) {
            $this->fail('There is nothing to approve yet: no student has complete scores.', $back);
        }
        $uid = (int) Auth::user()['id'];
        DB::transaction(function () use ($sid, $term, $class, $uid): void {
            DB::run(
                "UPDATE result_summaries SET status = 'approved', approved_by = ?, approved_at = ? WHERE school_id = ? AND term_id = ? AND class_id = ? AND status = 'draft'",
                [$uid, date('Y-m-d H:i:s'), $sid, $term['id'], $class['id']]
            );
            $this->record((int) $term['id'], (int) $class['id'], 'approve');
        });
        AuditLog::record('results.approved', 'class', $class['id'], ['term' => $term['name']]);
        Notify::schoolAdmins($sid, "Results approved: {$class['name']}", "{$term['name']} results for {$class['name']} were approved by " . Auth::user()['name'] . '.');
        $this->ok("Results for {$class['name']} approved. You can now publish them.", $back);
    }

    public function revert(): void
    {
        if (!Auth::can('results.approve')) {
            Http::abort(403, 'You do not have permission to do that.');
        }
        [$term, $class] = $this->target();
        $back = $this->back((int) $term['id'], (int) $class['id']);
        $n = DB::exec(
            "UPDATE result_summaries SET status = 'draft', approved_by = NULL, approved_at = NULL WHERE school_id = ? AND term_id = ? AND class_id = ? AND status = 'approved'",
            [$this->sid(), $term['id'], $class['id']]
        );
        if ($n === 0) {
            $this->fail('Only approved (unpublished) results can be reverted to draft.', $back);
        }
        $this->record((int) $term['id'], (int) $class['id'], 'revert');
        AuditLog::record('results.reverted', 'class', $class['id'], ['term' => $term['name']]);
        $this->ok('Results reverted to draft. Scores can be edited again.', $back);
    }

    public function publish(): void
    {
        if (!Auth::can('results.publish')) {
            Http::abort(403, 'You do not have permission to publish results.');
        }
        [$term, $class] = $this->target();
        $sid = $this->sid();
        $back = $this->back((int) $term['id'], (int) $class['id']);
        $n = DB::exec(
            "UPDATE result_summaries SET status = 'published', published_at = ? WHERE school_id = ? AND term_id = ? AND class_id = ? AND status = 'approved'",
            [date('Y-m-d H:i:s'), $sid, $term['id'], $class['id']]
        );
        if ($n === 0) {
            $this->fail('Only approved results can be published. Approve them first.', $back);
        }
        $this->record((int) $term['id'], (int) $class['id'], 'publish');
        AuditLog::record('results.published', 'class', $class['id'], ['term' => $term['name'], 'students' => $n]);
        Notify::schoolAdmins($sid, "Results published: {$class['name']}", "{$term['name']} results for {$class['name']} are now visible to students with a valid scratch card.");
        $this->ok("Results for {$class['name']} are published ($n students).", $back);
    }

    public function unpublish(): void
    {
        if (!Auth::can('results.publish')) {
            Http::abort(403, 'You do not have permission to unpublish results.');
        }
        [$term, $class] = $this->target();
        $back = $this->back((int) $term['id'], (int) $class['id']);
        $n = DB::exec(
            "UPDATE result_summaries SET status = 'approved', published_at = NULL WHERE school_id = ? AND term_id = ? AND class_id = ? AND status = 'published'",
            [$this->sid(), $term['id'], $class['id']]
        );
        if ($n === 0) {
            $this->fail('These results are not published.', $back);
        }
        $this->record((int) $term['id'], (int) $class['id'], 'unpublish');
        AuditLog::record('results.unpublished', 'class', $class['id'], ['term' => $term['name']]);
        Notify::schoolAdmins($this->sid(), "Results unpublished: {$class['name']}", "{$term['name']} results for {$class['name']} were withdrawn from the public portal.");
        $this->ok("Results for {$class['name']} were withdrawn from the portal.", $back);
    }

    // ---------------------------------------------------------------- comments

    public function comments(): void
    {
        [$term, $class] = [$this->owned('terms', (int) Http::input('term_id')), $this->owned('classes', (int) Http::input('class_id'))];
        $rows = DB::all(
            'SELECT rs.student_id, rs.teacher_comment, rs.principal_comment, rs.status, st.first_name, st.last_name, st.admission_no
             FROM result_summaries rs JOIN students st ON st.id = rs.student_id AND st.school_id = rs.school_id
             WHERE rs.school_id = ? AND rs.term_id = ? AND rs.class_id = ? ORDER BY st.last_name, st.first_name',
            [$this->sid(), $term['id'], $class['id']]
        );
        View::render('school/comments', ['title' => 'Report card comments', 'term' => $term, 'class' => $class, 'rows' => $rows]);
    }

    public function saveComments(): void
    {
        if (!Auth::can('scores.enter') && !Auth::can('results.approve')) {
            Http::abort(403, 'You do not have permission to edit comments.');
        }
        [$term, $class] = $this->target();
        $sid = $this->sid();
        $teacher = is_array($_POST['teacher'] ?? null) ? $_POST['teacher'] : [];
        $principal = is_array($_POST['principal'] ?? null) ? $_POST['principal'] : [];
        $isAdmin = Auth::role() === 'school_admin' || Auth::can('results.approve');
        $n = 0;
        DB::transaction(static function () use ($sid, $term, $class, $teacher, $principal, $isAdmin, &$n): void {
            foreach (DB::all('SELECT student_id FROM result_summaries WHERE school_id = ? AND term_id = ? AND class_id = ?', [$sid, $term['id'], $class['id']]) as $r) {
                $id = (int) $r['student_id'];
                $t = mb_substr(trim((string) ($teacher[$id] ?? '')), 0, 500);
                $p = mb_substr(trim((string) ($principal[$id] ?? '')), 0, 500);
                if ($isAdmin) {
                    $n += DB::exec('UPDATE result_summaries SET teacher_comment = ?, principal_comment = ? WHERE school_id = ? AND term_id = ? AND student_id = ?', [$t ?: null, $p ?: null, $sid, $term['id'], $id]);
                } else {
                    $n += DB::exec('UPDATE result_summaries SET teacher_comment = ? WHERE school_id = ? AND term_id = ? AND student_id = ?', [$t ?: null, $sid, $term['id'], $id]);
                }
            }
        });
        AuditLog::record('results.comments_saved', 'class', $class['id'], ['term' => $term['name']]);
        $this->ok('Comments saved.', "/school/results/comments?term_id={$term['id']}&class_id={$class['id']}");
    }

    // ---------------------------------------------------------------- exports

    private function csvOut(string $name, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($h, array_map('csv_safe', $r), ',', '"', '');
        }
        fclose($h);
        exit;
    }

    /** Class summary + subject broadsheet in one downloadable CSV. */
    public function export(): void
    {
        [$term, $class] = [$this->owned('terms', (int) Http::input('term_id')), $this->owned('classes', (int) Http::input('class_id'))];
        $sid = $this->sid();
        $subjects = DB::all('SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id AND s.school_id = cs.school_id WHERE cs.school_id = ? AND cs.class_id = ? ORDER BY s.name', [$sid, $class['id']]);
        $tot = [];
        foreach (DB::all('SELECT student_id, subject_id, total, grade FROM subject_results WHERE school_id = ? AND term_id = ? AND class_id = ?', [$sid, $term['id'], $class['id']]) as $r) {
            $tot[$r['student_id']][$r['subject_id']] = $r;
        }
        $cfg = DB::one('SELECT display_mode FROM school_settings WHERE school_id = ?', [$sid]);
        $showG = in_array($cfg['display_mode'], ['grades', 'both'], true);
        $showP = in_array($cfg['display_mode'], ['positions', 'both'], true);
        $head = array_merge(['admission_no', 'student'], array_column($subjects, 'name'), ['total', 'average'], $showP ? ['position'] : [], ['status']);
        $rows = [$head];
        foreach (DB::all(
            'SELECT rs.*, st.first_name, st.last_name, st.admission_no FROM result_summaries rs JOIN students st ON st.id = rs.student_id AND st.school_id = rs.school_id
             WHERE rs.school_id = ? AND rs.term_id = ? AND rs.class_id = ? ORDER BY CASE WHEN rs.position IS NULL THEN 1 ELSE 0 END, rs.position, st.last_name',
            [$sid, $term['id'], $class['id']]
        ) as $s) {
            $line = [$s['admission_no'], $s['last_name'] . ' ' . $s['first_name']];
            foreach ($subjects as $sub) {
                $r = $tot[$s['student_id']][$sub['id']] ?? null;
                $line[] = $r === null ? '' : (float) $r['total'] . ($showG && $r['grade'] !== null ? ' (' . $r['grade'] . ')' : '');
            }
            $line[] = (float) $s['total'];
            $line[] = (float) $s['average'];
            if ($showP) {
                $line[] = $s['position'] ?? '';
            }
            $line[] = $s['status'];
            $rows[] = $line;
        }
        AuditLog::record('results.exported', 'class', $class['id'], ['term' => $term['name']]);
        $this->csvOut('results-' . preg_replace('/[^A-Za-z0-9]+/', '-', $class['name'] . '-' . $term['name']) . '.csv', $rows);
    }

    // ---------------------------------------------------------------- analytics & archive

    public function analytics(): void
    {
        $sid = $this->sid();
        $terms = $this->terms();
        $termId = $this->currentTermId($terms);
        $classId = (int) Http::input('class_id');
        $data = null;
        if ($termId > 0 && $classId > 0
            && DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) > 0
            && DB::val('SELECT COUNT(*) FROM classes WHERE id = ? AND school_id = ?', [$classId, $sid]) > 0) {
            $data = ResultEngine::analytics($sid, $termId, $classId);
        }
        // Whole-school comparison for the chosen term.
        $compare = [];
        if ($termId > 0 && DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) > 0) {
            $compare = DB::all(
                'SELECT c.name, AVG(rs.average) AS avg, COUNT(*) AS n FROM result_summaries rs JOIN classes c ON c.id = rs.class_id AND c.school_id = rs.school_id
                 WHERE rs.school_id = ? AND rs.term_id = ? AND rs.subjects_count > 0 GROUP BY c.id, c.name, c.level_order ORDER BY c.level_order, c.name',
                [$sid, $termId]
            );
        }
        View::render('school/analytics', ['title' => 'Performance analytics', 'terms' => $terms, 'classes' => $this->classes(), 'termId' => $termId, 'classId' => $classId, 'stats' => $data, 'compare' => $compare]);
    }

    public function archive(): void
    {
        $sid = $this->sid();
        $rows = DB::all(
            "SELECT s.name AS session_name, t.id AS term_id, t.name AS term_name, c.id AS class_id, c.name AS class_name,
                    COUNT(*) AS students, MIN(rs.status) AS status_min, MAX(rs.status) AS status_max, MAX(rs.published_at) AS published_at, AVG(rs.average) AS avg
             FROM result_summaries rs
             JOIN terms t ON t.id = rs.term_id AND t.school_id = rs.school_id
             JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             JOIN classes c ON c.id = rs.class_id AND c.school_id = rs.school_id
             WHERE rs.school_id = ? GROUP BY s.name, t.id, t.name, c.id, c.name, c.level_order ORDER BY s.name DESC, t.id DESC, c.level_order",
            [$sid]
        );
        View::render('school/archive', ['title' => 'Results archive', 'rows' => $rows]);
    }
}
