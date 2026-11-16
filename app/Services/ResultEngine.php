<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * The result calculation engine. All maths runs here, on the backend, from the raw score records.
 * Money-style integer arithmetic (hundredths) is used for totals and ranking so ties are exact.
 */
final class ResultEngine
{
    // ---------------------------------------------------------------- pure helpers

    /** Hundredths as an integer, e.g. 19.5 → 1950. */
    public static function cents(float|int|string $v): int
    {
        return (int) round(((float) $v) * 100);
    }

    public static function percentage(int $totalCents, int $maxCents): float
    {
        return $maxCents > 0 ? round($totalCents / $maxCents * 100, 2) : 0.0;
    }

    /**
     * Grade for a percentage using the school's boundaries. The grade whose lower bound is the
     * highest one not above the score wins, so a small gap between boundaries can never strand a score.
     *
     * @param array<int,array{grade:string,min_score:mixed,remark:?string}> $bounds
     * @return array{0:?string,1:?string} [grade, remark]
     */
    public static function grade(float $pct, array $bounds): array
    {
        $best = null;
        $lowest = null;
        foreach ($bounds as $b) {
            $min = (float) $b['min_score'];
            if ($lowest === null || $min < (float) $lowest['min_score']) {
                $lowest = $b;
            }
            if ($pct + 0.000001 >= $min && ($best === null || $min > (float) $best['min_score'])) {
                $best = $b;
            }
        }
        $pick = $best ?? $lowest;
        return $pick === null ? [null, null] : [$pick['grade'], $pick['remark']];
    }

    /**
     * Positions for values where higher is better.
     * competition: 1,2,2,4   dense: 1,2,2,3
     *
     * @param array<int|string,int> $values key => hundredths
     * @return array<int|string,int> key => position
     */
    public static function rank(array $values, string $tieMode): array
    {
        arsort($values);
        $pos = [];
        $rank = 0;
        $i = 0;
        $prev = null;
        foreach ($values as $k => $v) {
            $i++;
            if ($prev === null || $v !== $prev) {
                $rank = $tieMode === 'dense' ? $rank + 1 : $i;
                $prev = $v;
            }
            $pos[$k] = $rank;
        }
        return $pos;
    }

    public static function ordinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }

    // ---------------------------------------------------------------- workflow state

    /** @return 'none'|'draft'|'approved'|'published'|'mixed' */
    public static function classStatus(int $sid, int $termId, int $classId): string
    {
        $st = array_column(DB::all(
            'SELECT DISTINCT status FROM result_summaries WHERE school_id = ? AND term_id = ? AND class_id = ?',
            [$sid, $termId, $classId]
        ), 'status');
        return match (count($st)) {
            0 => 'none',
            1 => $st[0],
            default => 'mixed',
        };
    }

    public static function isLocked(int $sid, int $termId, int $classId): bool
    {
        return in_array(self::classStatus($sid, $termId, $classId), ['approved', 'published', 'mixed'], true);
    }

    // ---------------------------------------------------------------- completeness

    /**
     * Which scores are still missing for a class in a term.
     * @return array{expected:int,entered:int,missing:array<int,array{student:string,subject:string,components:string[]}>,students:int,subjects:int}
     */
    public static function completeness(int $sid, int $termId, int $classId): array
    {
        $components = DB::all('SELECT id, name FROM assessment_components WHERE school_id = ? ORDER BY sort_order, id', [$sid]);
        $subjects = DB::all(
            'SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON s.id = cs.subject_id AND s.school_id = cs.school_id
             WHERE cs.school_id = ? AND cs.class_id = ? ORDER BY s.name',
            [$sid, $classId]
        );
        $students = DB::all(
            "SELECT id, first_name, last_name FROM students WHERE school_id = ? AND class_id = ? AND status = 'active' ORDER BY last_name, first_name",
            [$sid, $classId]
        );
        $have = [];
        foreach (DB::all(
            'SELECT sc.student_id, sc.subject_id, sc.component_id FROM scores sc JOIN students st ON st.id = sc.student_id AND st.school_id = sc.school_id
             WHERE sc.school_id = ? AND sc.term_id = ? AND st.class_id = ?',
            [$sid, $termId, $classId]
        ) as $r) {
            $have[$r['student_id'] . ':' . $r['subject_id'] . ':' . $r['component_id']] = true;
        }
        $missing = [];
        $entered = 0;
        foreach ($students as $st) {
            foreach ($subjects as $sub) {
                $miss = [];
                foreach ($components as $c) {
                    if (isset($have[$st['id'] . ':' . $sub['id'] . ':' . $c['id']])) {
                        $entered++;
                    } else {
                        $miss[] = $c['name'];
                    }
                }
                if ($miss) {
                    $missing[] = ['student' => $st['last_name'] . ' ' . $st['first_name'], 'subject' => $sub['name'], 'components' => $miss];
                }
            }
        }
        return [
            'expected' => count($students) * count($subjects) * count($components),
            'entered' => $entered, 'missing' => $missing,
            'students' => count($students), 'subjects' => count($subjects),
        ];
    }

    // ---------------------------------------------------------------- compute

    /**
     * Recomputes subject totals, grades, subject positions, student totals/averages and class positions.
     * Refused while the class's results are approved or published (unpublish / revert to draft first).
     *
     * @return array{students:int,subjects:int,computed:int}
     * @throws \RuntimeException
     */
    public static function compute(int $sid, int $termId, int $classId): array
    {
        if (self::isLocked($sid, $termId, $classId)) {
            throw new \RuntimeException('These results are approved or published and cannot be recomputed. Revert them to draft first.');
        }
        $settings = DB::one('SELECT tie_mode, position_by FROM school_settings WHERE school_id = ?', [$sid]) ?? ['tie_mode' => 'competition', 'position_by' => 'average'];
        $bounds = DB::all('SELECT grade, min_score, remark FROM grade_boundaries WHERE school_id = ?', [$sid]);
        $maxCents = 0;
        foreach (DB::all('SELECT max_score FROM assessment_components WHERE school_id = ?', [$sid]) as $c) {
            $maxCents += self::cents($c['max_score']);
        }
        $componentCount = (int) DB::val('SELECT COUNT(*) FROM assessment_components WHERE school_id = ?', [$sid]);
        if ($maxCents <= 0 || $componentCount === 0) {
            throw new \RuntimeException('No assessment components are configured. Set them up under School → Grading & scoring.');
        }
        $subjectIds = array_map('intval', array_column(DB::all('SELECT subject_id FROM class_subjects WHERE school_id = ? AND class_id = ?', [$sid, $classId]), 'subject_id'));
        $students = DB::all("SELECT id, arm_id FROM students WHERE school_id = ? AND class_id = ? AND status = 'active'", [$sid, $classId]);

        // Raw scores → [student][subject] = ['n' => components entered, 'sum' => hundredths]
        $acc = [];
        foreach (DB::all(
            'SELECT sc.student_id, sc.subject_id, sc.score FROM scores sc JOIN students st ON st.id = sc.student_id AND st.school_id = sc.school_id
             WHERE sc.school_id = ? AND sc.term_id = ? AND st.class_id = ?',
            [$sid, $termId, $classId]
        ) as $r) {
            $a = &$acc[(int) $r['student_id']][(int) $r['subject_id']];
            $a['n'] = ($a['n'] ?? 0) + 1;
            $a['sum'] = ($a['sum'] ?? 0) + self::cents($r['score']);
            unset($a);
        }

        // Subject totals (only fully scored subjects count) and subject positions.
        $totals = []; // [subject][student] = cents
        foreach ($students as $st) {
            foreach ($subjectIds as $subId) {
                $a = $acc[(int) $st['id']][$subId] ?? null;
                if ($a !== null && $a['n'] === $componentCount) {
                    $totals[$subId][(int) $st['id']] = $a['sum'];
                }
            }
        }
        $subjectPos = [];
        foreach ($totals as $subId => $byStudent) {
            $subjectPos[$subId] = self::rank($byStudent, $settings['tie_mode']);
        }

        // Per-student aggregates.
        $agg = [];
        foreach ($students as $st) {
            $sum = 0;
            $pctSum = 0.0;
            $n = 0;
            foreach ($subjectIds as $subId) {
                if (isset($totals[$subId][(int) $st['id']])) {
                    $t = $totals[$subId][(int) $st['id']];
                    $sum += $t;
                    $pctSum += self::percentage($t, $maxCents);
                    $n++;
                }
            }
            $agg[(int) $st['id']] = ['n' => $n, 'sum' => $sum, 'avg' => $n > 0 ? round($pctSum / $n, 2) : 0.0];
        }
        $rankInput = [];
        foreach ($agg as $stId => $a) {
            if ($a['n'] > 0) {
                $rankInput[$stId] = $settings['position_by'] === 'total' ? $a['sum'] : self::cents($a['avg']);
            }
        }
        $classPos = self::rank($rankInput, $settings['tie_mode']);
        $classSize = count($students);

        DB::transaction(static function () use ($sid, $termId, $classId, $students, $subjectIds, $totals, $subjectPos, $agg, $classPos, $classSize, $bounds, $maxCents): void {
            DB::run('DELETE FROM subject_results WHERE school_id = ? AND term_id = ? AND class_id = ?', [$sid, $termId, $classId]);
            foreach ($totals as $subId => $byStudent) {
                foreach ($byStudent as $stId => $cents) {
                    [$grade, $remark] = self::grade(self::percentage($cents, $maxCents), $bounds);
                    DB::insert(
                        'INSERT INTO subject_results (school_id, term_id, student_id, subject_id, class_id, total, grade, remark, subject_position) VALUES (?,?,?,?,?,?,?,?,?)',
                        [$sid, $termId, $stId, $subId, $classId, $cents / 100, $grade, $remark, $subjectPos[$subId][$stId] ?? null]
                    );
                }
            }
            $present = [];
            foreach ($students as $st) {
                $stId = (int) $st['id'];
                $present[] = $stId;
                $a = $agg[$stId];
                $vals = [$a['n'], $a['sum'] / 100, $a['avg'], $classPos[$stId] ?? null, $classSize, $st['arm_id']];
                $exists = DB::val('SELECT id FROM result_summaries WHERE term_id = ? AND student_id = ? AND school_id = ?', [$termId, $stId, $sid]);
                if ($exists !== null) {
                    DB::run(
                        'UPDATE result_summaries SET subjects_count = ?, total = ?, average = ?, position = ?, class_size = ?, arm_id = ?, class_id = ? WHERE id = ?',
                        array_merge($vals, [$classId, $exists])
                    );
                } else {
                    DB::insert(
                        "INSERT INTO result_summaries (school_id, term_id, student_id, class_id, subjects_count, total, average, position, class_size, arm_id, status) VALUES (?,?,?,?,?,?,?,?,?,?,'draft')",
                        [$sid, $termId, $stId, $classId, $a['n'], $a['sum'] / 100, $a['avg'], $classPos[$stId] ?? null, $classSize, $st['arm_id']]
                    );
                }
            }
            // Summaries of students who have since left this class are removed (drafts only reach here).
            foreach (DB::all('SELECT id, student_id FROM result_summaries WHERE school_id = ? AND term_id = ? AND class_id = ?', [$sid, $termId, $classId]) as $row) {
                if (!in_array((int) $row['student_id'], $present, true)) {
                    DB::run('DELETE FROM result_summaries WHERE id = ?', [$row['id']]);
                }
            }
        });

        return ['students' => $classSize, 'subjects' => count($subjectIds), 'computed' => count($rankInput)];
    }

    // ---------------------------------------------------------------- analytics

    /**
     * Class and subject performance for a term/class.
     * @return array{class_average:float,pass_rate:float,passed:int,ranked:int,subjects:array,grades:array<string,int>,top:array}
     */
    public static function analytics(int $sid, int $termId, int $classId): array
    {
        $passMark = (float) (DB::val('SELECT pass_mark FROM school_settings WHERE school_id = ?', [$sid]) ?? 40);
        $maxCents = 0;
        foreach (DB::all('SELECT max_score FROM assessment_components WHERE school_id = ?', [$sid]) as $c) {
            $maxCents += self::cents($c['max_score']);
        }
        $summ = DB::all(
            'SELECT rs.average, rs.position, st.first_name, st.last_name FROM result_summaries rs JOIN students st ON st.id = rs.student_id AND st.school_id = rs.school_id
             WHERE rs.school_id = ? AND rs.term_id = ? AND rs.class_id = ? AND rs.subjects_count > 0 ORDER BY rs.position, st.last_name',
            [$sid, $termId, $classId]
        );
        $avg = $summ ? round(array_sum(array_column($summ, 'average')) / count($summ), 2) : 0.0;
        $passed = count(array_filter($summ, static fn($r) => (float) $r['average'] >= $passMark));

        $subjects = [];
        foreach (DB::all(
            'SELECT sub.name, COUNT(*) AS n, AVG(r.total) AS avg_total, MAX(r.total) AS hi, MIN(r.total) AS lo
             FROM subject_results r JOIN subjects sub ON sub.id = r.subject_id AND sub.school_id = r.school_id
             WHERE r.school_id = ? AND r.term_id = ? AND r.class_id = ? GROUP BY sub.id, sub.name ORDER BY sub.name',
            [$sid, $termId, $classId]
        ) as $r) {
            $pass = (int) DB::val(
                'SELECT COUNT(*) FROM subject_results r JOIN subjects sub ON sub.id = r.subject_id AND sub.school_id = r.school_id
                 WHERE r.school_id = ? AND r.term_id = ? AND r.class_id = ? AND sub.name = ? AND r.total * 100.0 / ? >= ?',
                [$sid, $termId, $classId, $r['name'], max($maxCents / 100, 1), $passMark]
            );
            $subjects[] = [
                'name' => $r['name'], 'n' => (int) $r['n'],
                'average' => round((float) $r['avg_total'] / max($maxCents / 100, 1) * 100, 2),
                'highest' => (float) $r['hi'], 'lowest' => (float) $r['lo'],
                'pass_rate' => (int) $r['n'] > 0 ? round($pass / (int) $r['n'] * 100, 1) : 0.0,
            ];
        }
        $grades = [];
        foreach (DB::all('SELECT grade, COUNT(*) AS n FROM subject_results WHERE school_id = ? AND term_id = ? AND class_id = ? GROUP BY grade ORDER BY grade', [$sid, $termId, $classId]) as $g) {
            $grades[(string) $g['grade']] = (int) $g['n'];
        }
        return [
            'class_average' => $avg, 'pass_rate' => $summ ? round($passed / count($summ) * 100, 1) : 0.0,
            'passed' => $passed, 'ranked' => count($summ), 'subjects' => $subjects, 'grades' => $grades,
            'top' => array_slice($summ, 0, 5),
        ];
    }
}
