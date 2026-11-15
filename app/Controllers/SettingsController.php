<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{AuditLog, DB, Http, View};

/** School-level result configuration: display mode, positions, grading scale and assessment components. */
final class SettingsController extends BaseController
{
    private const BACK = '/school/settings';

    public function show(): void
    {
        $sid = $this->sid();
        $usage = [];
        foreach (DB::all('SELECT component_id, COUNT(*) AS n FROM scores WHERE school_id = ? GROUP BY component_id', [$sid]) as $r) {
            $usage[$r['component_id']] = (int) $r['n'];
        }
        View::render('school/settings', [
            'title' => 'Grading & scoring',
            'cfg' => DB::one('SELECT * FROM school_settings WHERE school_id = ?', [$sid]),
            'grades' => DB::all('SELECT * FROM grade_boundaries WHERE school_id = ? ORDER BY min_score DESC', [$sid]),
            'components' => DB::all('SELECT * FROM assessment_components WHERE school_id = ? ORDER BY sort_order, id', [$sid]),
            'usage' => $usage,
        ]);
    }

    public function saveGeneral(): void
    {
        $display = Http::input('display_mode');
        $tie = Http::input('tie_mode');
        $by = Http::input('position_by');
        $binding = Http::input('card_binding');
        $pass = str_replace(',', '.', Http::input('pass_mark'));
        $principal = $this->text('principal_name', 150);
        $footer = $this->text('report_footer', 255);
        if (!in_array($display, ['grades', 'positions', 'both'], true) || !in_array($tie, ['competition', 'dense'], true)
            || !in_array($by, ['average', 'total'], true) || !in_array($binding, ['first_student', 'none'], true)) {
            $this->fail('Choose a valid option for every setting.', self::BACK);
        }
        if (!is_numeric($pass) || (float) $pass < 0 || (float) $pass > 100) {
            $this->fail('The pass mark must be between 0 and 100.', self::BACK);
        }
        if ($principal === null || $footer === null) {
            $this->fail('The principal name or report footer is too long.', self::BACK);
        }
        DB::run(
            'UPDATE school_settings SET display_mode=?, tie_mode=?, position_by=?, pass_mark=?, card_binding=?, allow_download=?, principal_name=?, report_footer=? WHERE school_id=?',
            [$display, $tie, $by, (float) $pass, $binding, Http::input('allow_download') === '1' ? 1 : 0, $principal ?: null, $footer ?: null, $this->sid()]
        );
        AuditLog::record('settings.updated', 'school_settings', $this->sid(), ['display_mode' => $display, 'tie_mode' => $tie, 'position_by' => $by, 'card_binding' => $binding]);
        $this->ok('Settings saved. Position changes apply the next time results are computed; the display option applies immediately.', self::BACK);
    }

    public function saveGrades(): void
    {
        $names = is_array($_POST['grade'] ?? null) ? $_POST['grade'] : [];
        $mins = is_array($_POST['min'] ?? null) ? $_POST['min'] : [];
        $remarks = is_array($_POST['remark'] ?? null) ? $_POST['remark'] : [];
        $rows = [];
        $seenNames = [];
        $seenMins = [];
        foreach ($names as $i => $n) {
            $n = trim((string) $n);
            $m = str_replace(',', '.', trim((string) ($mins[$i] ?? '')));
            if ($n === '' && $m === '') {
                continue; // blank spare row
            }
            if ($n === '' || mb_strlen($n) > 5 || !is_numeric($m) || (float) $m < 0 || (float) $m > 100) {
                $this->fail('Each grade needs a name (up to 5 characters) and a minimum percentage between 0 and 100.', self::BACK);
            }
            $key = mb_strtolower($n);
            if (isset($seenNames[$key]) || isset($seenMins[(string) (float) $m])) {
                $this->fail('Grade names and minimum scores must each be unique.', self::BACK);
            }
            $seenNames[$key] = $seenMins[(string) (float) $m] = true;
            $remark = mb_substr(trim((string) ($remarks[$i] ?? '')), 0, 60);
            $rows[] = ['grade' => $n, 'min' => round((float) $m, 2), 'remark' => $remark];
        }
        if (count($rows) < 2 || count($rows) > 12) {
            $this->fail('Define between 2 and 12 grades.', self::BACK);
        }
        usort($rows, static fn($a, $b) => $b['min'] <=> $a['min']);
        if ($rows[count($rows) - 1]['min'] != 0.0) {
            $this->fail('The lowest grade must start at 0 so every score receives a grade.', self::BACK);
        }
        $sid = $this->sid();
        DB::transaction(static function () use ($sid, $rows): void {
            DB::run('DELETE FROM grade_boundaries WHERE school_id = ?', [$sid]);
            foreach ($rows as $i => $r) {
                $max = $i === 0 ? 100.0 : round($rows[$i - 1]['min'] - 0.01, 2);
                DB::insert(
                    'INSERT INTO grade_boundaries (school_id, grade, min_score, max_score, remark, sort_order) VALUES (?,?,?,?,?,?)',
                    [$sid, $r['grade'], $r['min'], $max, $r['remark'] ?: null, $i]
                );
            }
        });
        AuditLog::record('grading.updated', 'grade_boundaries', null, ['grades' => count($rows)]);
        $this->ok('Grading scale saved. Recompute results for a class to apply it to existing scores.', self::BACK);
    }

    public function saveComponents(): void
    {
        $sid = $this->sid();
        $ids = is_array($_POST['id'] ?? null) ? $_POST['id'] : [];
        $names = is_array($_POST['name'] ?? null) ? $_POST['name'] : [];
        $maxes = is_array($_POST['max'] ?? null) ? $_POST['max'] : [];
        $remove = is_array($_POST['remove'] ?? null) ? array_map('intval', array_keys($_POST['remove'])) : [];
        $examIdx = Http::input('exam');

        $existing = [];
        foreach (DB::all('SELECT id, max_score FROM assessment_components WHERE school_id = ?', [$sid]) as $c) {
            $existing[(int) $c['id']] = (float) $c['max_score'];
        }
        $used = [];
        foreach (DB::all('SELECT DISTINCT component_id FROM scores WHERE school_id = ?', [$sid]) as $r) {
            $used[(int) $r['component_id']] = true;
        }

        $plan = [];
        $seen = [];
        foreach ($names as $i => $n) {
            $n = trim((string) $n);
            $id = (int) ($ids[$i] ?? 0);
            $max = str_replace(',', '.', trim((string) ($maxes[$i] ?? '')));
            if ($id > 0 && !isset($existing[$id])) {
                Http::abort(404); // a component id from another school (or nonsense)
            }
            if ($id > 0 && in_array($id, $remove, true)) {
                if (isset($used[$id])) {
                    $this->fail("\"$n\" already has scores and cannot be removed.", self::BACK);
                }
                $plan[] = ['op' => 'delete', 'id' => $id];
                continue;
            }
            if ($n === '' && $max === '' && $id === 0) {
                continue;
            }
            if ($n === '' || mb_strlen($n) > 60 || !is_numeric($max) || (float) $max <= 0 || (float) $max > 999) {
                $this->fail('Each component needs a name and a maximum score above 0.', self::BACK);
            }
            if (isset($seen[mb_strtolower($n)])) {
                $this->fail('Component names must be unique.', self::BACK);
            }
            $seen[mb_strtolower($n)] = true;
            if ($id > 0 && isset($used[$id]) && abs($existing[$id] - (float) $max) > 0.0001) {
                $this->fail("\"$n\" already has scores, so its maximum score cannot change.", self::BACK);
            }
            $plan[] = ['op' => $id > 0 ? 'update' : 'insert', 'id' => $id, 'name' => $n, 'max' => round((float) $max, 2), 'order' => count($plan), 'exam' => (string) $i === $examIdx ? 1 : 0];
        }
        $active = array_values(array_filter($plan, static fn($p) => $p['op'] !== 'delete'));
        if (count($active) < 1 || count($active) > 10) {
            $this->fail('Define between 1 and 10 assessment components.', self::BACK);
        }
        DB::transaction(static function () use ($sid, $plan): void {
            foreach ($plan as $p) {
                if ($p['op'] === 'delete') {
                    DB::run('DELETE FROM assessment_components WHERE id = ? AND school_id = ?', [$p['id'], $sid]);
                } elseif ($p['op'] === 'update') {
                    DB::run('UPDATE assessment_components SET name=?, max_score=?, sort_order=?, is_exam=? WHERE id=? AND school_id=?', [$p['name'], $p['max'], $p['order'], $p['exam'], $p['id'], $sid]);
                } else {
                    DB::insert('INSERT INTO assessment_components (school_id, name, max_score, sort_order, is_exam) VALUES (?,?,?,?,?)', [$sid, $p['name'], $p['max'], $p['order'], $p['exam']]);
                }
            }
        });
        $total = array_sum(array_column($active, 'max'));
        AuditLog::record('components.updated', 'assessment_components', null, ['count' => count($active), 'total' => $total]);
        $this->ok('Assessment components saved. Total marks: ' . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') . '.', self::BACK);
    }
}
