<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, AuditLog, DB, Http, View};
use App\Services\{Importer, ResultEngine, ScoreService};

/** Score entry, bulk import and correction history. Teachers only reach classes/subjects assigned to them. */
final class ScoresController extends BaseController
{
    private function terms(): array
    {
        return DB::all(
            'SELECT t.id, t.name, t.is_current, s.name AS session_name FROM terms t JOIN academic_sessions s ON s.id = t.session_id AND s.school_id = t.school_id
             WHERE t.school_id = ? ORDER BY s.name DESC, t.id DESC',
            [$this->sid()]
        );
    }

    private function isAdmin(): bool
    {
        return Auth::role() === 'school_admin';
    }

    private function allowed(int $classId, int $subjectId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return Auth::can('scores.enter') && (int) DB::val(
            'SELECT COUNT(*) FROM teacher_assignments WHERE school_id = ? AND user_id = ? AND class_id = ? AND subject_id = ?',
            [$this->sid(), Auth::user()['id'], $classId, $subjectId]
        ) > 0;
    }

    private function classes(): array
    {
        $sid = $this->sid();
        if ($this->isAdmin()) {
            return DB::all('SELECT id, name FROM classes WHERE school_id = ? ORDER BY level_order, name', [$sid]);
        }
        return DB::all(
            'SELECT DISTINCT c.id, c.name, c.level_order FROM teacher_assignments t JOIN classes c ON c.id = t.class_id AND c.school_id = t.school_id
             WHERE t.school_id = ? AND t.user_id = ? ORDER BY c.level_order, c.name',
            [$sid, Auth::user()['id']]
        );
    }

    private function subjectsFor(int $classId): array
    {
        $sid = $this->sid();
        if ($this->isAdmin()) {
            return DB::all(
                'SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id AND s.school_id = cs.school_id WHERE cs.school_id = ? AND cs.class_id = ? ORDER BY s.name',
                [$sid, $classId]
            );
        }
        return DB::all(
            'SELECT s.id, s.name FROM teacher_assignments t JOIN subjects s ON s.id = t.subject_id AND s.school_id = t.school_id WHERE t.school_id = ? AND t.user_id = ? AND t.class_id = ? ORDER BY s.name',
            [$sid, Auth::user()['id'], $classId]
        );
    }

    /** Resolves and authorises the term/class/subject selection (GET or POST). Aborts on tampering. */
    private function selection(): array
    {
        $term = $this->owned('terms', (int) Http::input('term_id'));
        $class = $this->owned('classes', (int) Http::input('class_id'));
        $subject = $this->owned('subjects', (int) Http::input('subject_id'));
        $assigned = (int) DB::val('SELECT COUNT(*) FROM class_subjects WHERE school_id = ? AND class_id = ? AND subject_id = ?', [$this->sid(), $class['id'], $subject['id']]);
        if ($assigned === 0 || !$this->allowed((int) $class['id'], (int) $subject['id'])) {
            Http::abort(403, 'You are not assigned to enter scores for that class and subject.');
        }
        return [$term, $class, $subject];
    }

    private function components(): array
    {
        return DB::all('SELECT id, name, max_score FROM assessment_components WHERE school_id = ? ORDER BY sort_order, id', [$this->sid()]);
    }

    private function students(int $classId): array
    {
        return DB::all(
            "SELECT id, admission_no, first_name, last_name FROM students WHERE school_id = ? AND class_id = ? AND status = 'active' ORDER BY last_name, first_name",
            [$this->sid(), $classId]
        );
    }

    public function index(): void
    {
        $sid = $this->sid();
        $terms = $this->terms();
        $termId = (int) Http::input('term_id');
        if ($termId === 0) {
            foreach ($terms as $t) {
                if ((int) $t['is_current'] === 1) {
                    $termId = (int) $t['id'];
                }
            }
        }
        $classId = (int) Http::input('class_id');
        $subjectId = (int) Http::input('subject_id');
        $classes = $this->classes();
        $classOk = in_array($classId, array_map('intval', array_column($classes, 'id')), true);
        $subjects = $classOk ? $this->subjectsFor($classId) : [];
        $subjectOk = in_array($subjectId, array_map('intval', array_column($subjects, 'id')), true);

        $grid = null;
        if ($termId > 0 && $classOk && $subjectOk && DB::val('SELECT COUNT(*) FROM terms WHERE id = ? AND school_id = ?', [$termId, $sid]) > 0) {
            $students = $this->students($classId);
            $scores = [];
            foreach (DB::all('SELECT student_id, component_id, score FROM scores WHERE school_id = ? AND term_id = ? AND subject_id = ?', [$sid, $termId, $subjectId]) as $r) {
                $scores[$r['student_id']][$r['component_id']] = rtrim(rtrim(number_format((float) $r['score'], 2, '.', ''), '0'), '.');
            }
            $grid = [
                'students' => $students, 'components' => $this->components(), 'scores' => $scores,
                'locked' => ResultEngine::isLocked($sid, $termId, $classId),
                'history' => DB::all(
                    'SELECT h.changed_at, h.old_score, h.new_score, h.action, h.reason, st.first_name, st.last_name, c.name AS component, u.name AS by_name
                     FROM score_history h
                     JOIN students st ON st.id = h.student_id AND st.school_id = h.school_id
                     JOIN assessment_components c ON c.id = h.component_id AND c.school_id = h.school_id
                     LEFT JOIN users u ON u.id = h.changed_by
                     WHERE h.school_id = ? AND h.term_id = ? AND h.subject_id = ? AND st.class_id = ? ORDER BY h.id DESC LIMIT 15',
                    [$sid, $termId, $subjectId, $classId]
                ),
            ];
        }
        View::render('school/scores', [
            'title' => 'Score entry', 'terms' => $terms, 'classes' => $classes, 'subjects' => $subjects,
            'termId' => $termId, 'classId' => $classId, 'subjectId' => $subjectId, 'grid' => $grid,
        ]);
    }

    /** Parses one posted score. @return array{0:?float,1:?string} value (null = empty) and error */
    private function parse(string $raw, float $max): array
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '') {
            return [null, null];
        }
        if (!is_numeric($raw)) {
            return [null, 'not a number'];
        }
        $v = round((float) $raw, 2);
        if ($v < 0 || $v > $max) {
            return [null, 'must be between 0 and ' . $max];
        }
        return [$v, null];
    }

    private function back(array $sel): string
    {
        return '/school/scores?term_id=' . $sel[0]['id'] . '&class_id=' . $sel[1]['id'] . '&subject_id=' . $sel[2]['id'];
    }

    public function save(): void
    {
        $sel = $this->selection();
        [$term, $class, $subject] = $sel;
        $sid = $this->sid();
        if (ResultEngine::isLocked($sid, (int) $term['id'], (int) $class['id'])) {
            $this->fail('These results are approved or published, so scores are locked. Ask an administrator to revert them to draft.', $this->back($sel));
        }
        $components = [];
        foreach ($this->components() as $c) {
            $components[(int) $c['id']] = $c;
        }
        $studentIds = array_map('intval', array_column($this->students((int) $class['id']), 'id'));
        $posted = is_array($_POST['scores'] ?? null) ? $_POST['scores'] : [];

        $cells = [];
        $errors = [];
        foreach ($posted as $stId => $byComp) {
            if (!in_array((int) $stId, $studentIds, true) || !is_array($byComp)) {
                continue; // ignore ids that are not in this class
            }
            foreach ($byComp as $cId => $raw) {
                if (!isset($components[(int) $cId]) || !is_string($raw)) {
                    continue;
                }
                [$v, $err] = $this->parse($raw, (float) $components[(int) $cId]['max_score']);
                if ($err !== null) {
                    $errors[] = 'Student #' . (int) $stId . ' ' . $components[(int) $cId]['name'] . ': ' . $err;
                    continue;
                }
                $cells[(int) $stId][(int) $cId] = $v;
            }
        }
        if ($errors) {
            $this->fail('Nothing was saved. ' . count($errors) . ' score(s) are invalid. First problem: ' . $errors[0], $this->back($sel));
        }
        $reason = $this->text('reason', 255) ?: null;
        $stats = ScoreService::save($sid, (int) $term['id'], (int) $subject['id'], $cells, 'save', $reason, (int) Auth::user()['id']);
        AuditLog::record('scores.saved', 'subject', $subject['id'], $stats + ['class' => $class['name'], 'term' => $term['name']]);
        $this->ok(sprintf('Scores saved: %d new, %d changed, %d cleared.', $stats['created'], $stats['updated'], $stats['deleted']), $this->back($sel));
    }

    public function template(): void
    {
        [$term, $class, $subject] = $this->selection();
        $comps = $this->components();
        $existing = [];
        foreach (DB::all('SELECT student_id, component_id, score FROM scores WHERE school_id = ? AND term_id = ? AND subject_id = ?', [$this->sid(), $term['id'], $subject['id']]) as $r) {
            $existing[$r['student_id']][$r['component_id']] = $r['score'];
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scores-' . preg_replace('/[^A-Za-z0-9]+/', '-', $class['name'] . '-' . $subject['name']) . '.csv"');
        $h = fopen('php://output', 'w');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_merge(['admission_no', 'student_name'], array_map(static fn($c) => $c['name'], $comps)), ',', '"', '');
        foreach ($this->students((int) $class['id']) as $s) {
            $row = [csv_safe($s['admission_no']), csv_safe($s['last_name'] . ' ' . $s['first_name'])];
            foreach ($comps as $c) {
                $row[] = isset($existing[$s['id']][$c['id']]) ? (string) (float) $existing[$s['id']][$c['id']] : '';
            }
            fputcsv($h, $row, ',', '"', '');
        }
        fclose($h);
        exit;
    }

    /** Bulk import is all-or-nothing: any invalid cell rejects the whole file so nothing half-applies. */
    public function import(): void
    {
        $sel = $this->selection();
        [$term, $class, $subject] = $sel;
        $sid = $this->sid();
        if (ResultEngine::isLocked($sid, (int) $term['id'], (int) $class['id'])) {
            $this->fail('These results are approved or published, so scores are locked.', $this->back($sel));
        }
        try {
            $data = Importer::read($_FILES['file'] ?? []);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), $this->back($sel));
        }
        $key = static fn(string $n): string => trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($n)), '_');
        $compByKey = [];
        foreach ($this->components() as $c) {
            $compByKey[$key($c['name'])] = $c;
        }
        $admCol = null;
        foreach (['admission_no', 'admission_number', 'student_id', 'reg_no'] as $cand) {
            if (in_array($cand, $data['headers'], true)) {
                $admCol = $cand;
                break;
            }
        }
        $usable = array_values(array_filter($data['headers'], static fn($h) => isset($compByKey[$h])));
        if ($admCol === null || !$usable) {
            $this->fail('The file needs an "admission_no" column and at least one column named exactly like an assessment component (' . implode(', ', array_map(static fn($c) => $c['name'], $compByKey)) . '). Download the template.', $this->back($sel));
        }
        $byAdm = [];
        foreach ($this->students((int) $class['id']) as $s) {
            $byAdm[mb_strtolower($s['admission_no'])] = (int) $s['id'];
        }

        $cells = [];
        $errors = [];
        foreach ($data['rows'] as $line => $row) {
            $stId = $byAdm[mb_strtolower($row[$admCol] ?? '')] ?? 0;
            if ($stId === 0) {
                $errors[] = "Row $line: student ID \"" . ($row[$admCol] ?? '') . '" is not an active student of ' . $class['name'] . '.';
                continue;
            }
            foreach ($usable as $h) {
                [$v, $err] = $this->parse($row[$h] ?? '', (float) $compByKey[$h]['max_score']);
                if ($err !== null) {
                    $errors[] = "Row $line, {$compByKey[$h]['name']}: $err.";
                } elseif ($v !== null) { // blank cells are skipped, never cleared, by an import
                    $cells[$stId][(int) $compByKey[$h]['id']] = $v;
                }
            }
        }
        if ($errors) {
            $shown = array_slice($errors, 0, 8);
            $this->fail('Import rejected, nothing was saved (' . count($errors) . ' problem(s)). ' . implode(' ', $shown), $this->back($sel));
        }
        $stats = ScoreService::save($sid, (int) $term['id'], (int) $subject['id'], $cells, 'import', 'Bulk import', (int) Auth::user()['id']);
        AuditLog::record('scores.imported', 'subject', $subject['id'], $stats + ['class' => $class['name']]);
        $this->ok(sprintf('Import complete: %d new, %d changed, %d unchanged.', $stats['created'], $stats['updated'], $stats['unchanged']), $this->back($sel));
    }
}
