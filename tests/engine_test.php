<?php
declare(strict_types=1);
/** Result engine tests. usage: php tests/engine_test.php <scratch-dir> */
require __DIR__ . '/tools/bootstrap_test.php';
test_boot(rtrim($argv[1] ?? sys_get_temp_dir(), '/') . '/engine.sqlite');

use App\Core\DB;
use App\Services\ResultEngine as E;
use App\Services\ScoreService;

$pass = $fail = 0;
$t = static function (string $label, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { $pass++; echo "PASS  $label\n"; } else { $fail++; echo "FAIL  $label $detail\n"; }
};

// ---- pure functions
$bounds = [['grade'=>'A','min_score'=>70,'remark'=>'Excellent'],['grade'=>'B','min_score'=>60,'remark'=>'Very Good'],['grade'=>'C','min_score'=>50,'remark'=>'Good'],['grade'=>'D','min_score'=>45,'remark'=>'Fair'],['grade'=>'E','min_score'=>40,'remark'=>'Pass'],['grade'=>'F','min_score'=>0,'remark'=>'Fail']];
$t('grade 70 -> A (boundary inclusive)', E::grade(70.0, $bounds)[0] === 'A');
$t('grade 69.99 -> B', E::grade(69.99, $bounds)[0] === 'B');
$t('grade 40 -> E', E::grade(40.0, $bounds)[0] === 'E');
$t('grade 39.99 -> F', E::grade(39.99, $bounds)[0] === 'F');
$t('grade 0 -> F', E::grade(0.0, $bounds)[0] === 'F');
$t('grade 100 -> A', E::grade(100.0, $bounds)[0] === 'A');
$gappy = [['grade'=>'A','min_score'=>80,'remark'=>null],['grade'=>'B','min_score'=>50,'remark'=>null]];
$t('score below lowest boundary falls to lowest grade (never null)', E::grade(10.0, $gappy)[0] === 'B');
$t('cents() avoids float drift (0.1+0.2, 19.1)', E::cents('19.1') === 1910 && E::cents(0.1 + 0.2) === 30);
$t('percentage 548/600', E::percentage(54800, 60000) === 91.33);
$t('percentage with zero max is 0', E::percentage(100, 0) === 0.0);
$vals = ['a'=>9000,'b'=>8000,'c'=>8000,'d'=>7000];
$t('competition ties 1,2,2,4', array_values(E::rank($vals, 'competition')) === [1,2,2,4]);
$t('dense ties 1,2,2,3', array_values(E::rank($vals, 'dense')) === [1,2,2,3]);
$t('all tied -> everyone is 1st', array_values(E::rank(['a'=>5,'b'=>5,'c'=>5], 'competition')) === [1,1,1]);
$t('ordinals 1st 2nd 3rd 4th 11th 12th 13th 21st 22nd 101st 111th', array_map([E::class,'ordinal'], [1,2,3,4,11,12,13,21,22,101,111]) === ['1st','2nd','3rd','4th','11th','12th','13th','21st','22nd','101st','111th']);

// ---- compute against the Rich Harvest style sample: six subjects, 87+95+92+92+96+86 = 548 of 600
$S = seed_school('Engine School', 4, ['English','Maths','Science','Civic','Computer','Arts']);
$sid = $S['sid']; $term = $S['term']; $class = $S['class']; [$c1,$c2,$c3] = $S['comps'];
$mk = static function (int $total): array { $a = (int) floor($total * 0.2); $b = (int) floor($total * 0.2); return [$a, $b, $total - $a - $b]; };
$totals = [[87,95,92,92,96,86],[80,80,80,80,80,80],[80,80,80,80,80,80],[50,50,50,50,50,50]];
foreach ($S['students'] as $i => $stu) {
    foreach ($S['subjects'] as $j => $sub) {
        [$a,$b,$c] = $mk($totals[$i][$j]);
        ScoreService::save($sid, $term, $sub, [$stu => [$c1 => (float)$a, $c2 => (float)$b, $c3 => (float)$c]], 'save', null, null);
    }
}
$r = E::compute($sid, $term, $class);
$t('compute reports 4 students ranked', $r['students'] === 4 && $r['computed'] === 4, json_encode($r));
$sum = fn(int $i) => DB::one('SELECT * FROM result_summaries WHERE school_id=? AND term_id=? AND student_id=?', [$sid, $term, $S['students'][$i]]);
$s0 = $sum(0);
$t('sample student total = 548', (float)$s0['total'] === 548.0, (string)$s0['total']);
$t('sample student average = 91.33', (float)$s0['average'] === 91.33, (string)$s0['average']);
$t('sample student position 1', (int)$s0['position'] === 1);
$t('two equal students share position 2 (competition)', (int)$sum(1)['position'] === 2 && (int)$sum(2)['position'] === 2);
$t('next student is position 4 (competition skips 3)', (int)$sum(3)['position'] === 4);
$t('class size recorded', (int)$s0['class_size'] === 4);
$g = DB::val('SELECT grade FROM subject_results WHERE term_id=? AND student_id=? AND subject_id=?', [$term, $S['students'][0], $S['subjects'][0]]);
$t('87/100 is grade A', $g === 'A', (string)$g);
$t('subject positions assigned', (int)DB::val('SELECT subject_position FROM subject_results WHERE term_id=? AND student_id=? AND subject_id=?', [$term, $S['students'][0], $S['subjects'][1]]) === 1);

// dense mode
DB::run("UPDATE school_settings SET tie_mode='dense' WHERE school_id=?", [$sid]);
E::compute($sid, $term, $class);
$t('dense mode: positions 1,2,2,3', [(int)$sum(0)['position'], (int)$sum(1)['position'], (int)$sum(2)['position'], (int)$sum(3)['position']] === [1,2,2,3]);

// incomplete data: missing component means subject is not counted
DB::run('DELETE FROM scores WHERE school_id=? AND student_id=? AND subject_id=? AND component_id=?', [$sid, $S['students'][3], $S['subjects'][0], $c3]);
E::compute($sid, $term, $class);
$t('incomplete subject excluded from count', (int)$sum(3)['subjects_count'] === 5);
$comp = E::completeness($sid, $term, $class);
$t('completeness check reports the gap', !empty($comp) && json_encode($comp) !== '[]');

// audit history
$t('score history rows written on save', (int)DB::val('SELECT COUNT(*) FROM score_history WHERE school_id=?', [$sid]) >= 72);
ScoreService::save($sid, $term, $S['subjects'][0], [$S['students'][0] => [$c1 => 19.0]], 'save', 'correction', null);
$h = DB::one("SELECT * FROM score_history WHERE school_id=? AND student_id=? AND subject_id=? AND component_id=? AND action='update' ORDER BY id DESC", [$sid, $S['students'][0], $S['subjects'][0], $c1]);
$t('correction recorded with old and new value + reason', $h !== null && $h['reason'] === 'correction' && (float)$h['new_score'] === 19.0 && $h['old_score'] !== null);

// lock
DB::run("UPDATE result_summaries SET status='approved' WHERE school_id=? AND term_id=?", [$sid, $term]);
$threw = false; try { E::compute($sid, $term, $class); } catch (RuntimeException) { $threw = true; }
$t('compute refused while approved', $threw);
$t('isLocked true when approved', E::isLocked($sid, $term, $class));

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
