<?php
declare(strict_types=1);

/**
 * Proves the DATABASE refuses cross-school references (composite tenant foreign keys),
 * independent of any application code. Runs on the SQLite test copy of the schema.
 * usage: php tests/tenant_isolation_db.php <scratch-dir>
 */

use App\Core\DB;

$tmp = $argv[1] ?? sys_get_temp_dir();
$dbFile = rtrim($tmp, '/') . '/tenant.sqlite';
passthru('php ' . escapeshellarg(__DIR__ . '/tools/make_test_db.php') . ' ' . escapeshellarg($dbFile) . ' >/dev/null', $rc);
if ($rc !== 0) {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $dbFile);
require BASE_PATH . '/app/bootstrap.php';

$pass = 0;
$fail = 0;
$expect = static function (string $label, callable $fn, bool $shouldThrow) use (&$pass, &$fail): void {
    try {
        $fn();
        $threw = false;
    } catch (PDOException) {
        $threw = true;
    }
    if ($threw === $shouldThrow) {
        $pass++;
        echo "PASS  $label\n";
    } else {
        $fail++;
        echo "FAIL  $label\n";
    }
};

$A = (int) DB::val("SELECT id FROM schools WHERE slug = 'alpha'");
$B = (int) DB::val("SELECT id FROM schools WHERE slug = 'beta'");

$classA = DB::insert('INSERT INTO classes (school_id, name) VALUES (?, ?)', [$A, 'JSS 1']);
$classB = DB::insert('INSERT INTO classes (school_id, name) VALUES (?, ?)', [$B, 'JSS 1']);
$subjA = DB::insert('INSERT INTO subjects (school_id, name) VALUES (?, ?)', [$A, 'Mathematics']);
$subjB = DB::insert('INSERT INTO subjects (school_id, name) VALUES (?, ?)', [$B, 'Mathematics']);

$expect('student in school A -> class of school A is accepted', fn() => DB::insert(
    'INSERT INTO students (school_id, admission_no, first_name, last_name, class_id) VALUES (?,?,?,?,?)', [$A, 'A001', 'Ada', 'Obi', $classA]), false);

$expect('student in school A -> class of school B is REJECTED', fn() => DB::insert(
    'INSERT INTO students (school_id, admission_no, first_name, last_name, class_id) VALUES (?,?,?,?,?)', [$A, 'A002', 'Eve', 'Obi', $classB]), true);

$expect('same admission_no in two schools is allowed', function () use ($B, $classB) {
    DB::insert('INSERT INTO students (school_id, admission_no, first_name, last_name, class_id) VALUES (?,?,?,?,?)', [$B, 'A001', 'Bola', 'Ade', $classB]);
}, false);

$expect('duplicate admission_no within one school is REJECTED', fn() => DB::insert(
    'INSERT INTO students (school_id, admission_no, first_name, last_name, class_id) VALUES (?,?,?,?,?)', [$A, 'A001', 'Dup', 'Dup', $classA]), true);

$expect('class_subject: school A class + school B subject is REJECTED', fn() => DB::insert(
    'INSERT INTO class_subjects (school_id, class_id, subject_id) VALUES (?,?,?)', [$A, $classA, $subjB]), true);

$expect('class_subject: both from school A is accepted', fn() => DB::insert(
    'INSERT INTO class_subjects (school_id, class_id, subject_id) VALUES (?,?,?)', [$A, $classA, $subjA]), false);

// Term / session / score chain
$sessA = DB::insert('INSERT INTO academic_sessions (school_id, name) VALUES (?,?)', [$A, '2026/2027']);
$sessB = DB::insert('INSERT INTO academic_sessions (school_id, name) VALUES (?,?)', [$B, '2026/2027']);
$expect('term for school A under school B session is REJECTED', fn() => DB::insert(
    'INSERT INTO terms (school_id, session_id, name) VALUES (?,?,?)', [$A, $sessB, 'First Term']), true);
$termA = DB::insert('INSERT INTO terms (school_id, session_id, name) VALUES (?,?,?)', [$A, $sessA, 'First Term']);
$compA = DB::insert('INSERT INTO assessment_components (school_id, name, max_score) VALUES (?,?,?)', [$A, 'Exam', 60]);
$studA = (int) DB::val("SELECT id FROM students WHERE school_id = ? AND admission_no = 'A001'", [$A]);
$studB = (int) DB::val("SELECT id FROM students WHERE school_id = ? AND admission_no = 'A001'", [$B]);

$expect('score row with school B student under school A is REJECTED', fn() => DB::insert(
    'INSERT INTO scores (school_id, term_id, student_id, subject_id, component_id, score) VALUES (?,?,?,?,?,?)', [$A, $termA, $studB, $subjA, $compA, 50]), true);
$expect('valid score row is accepted', fn() => DB::insert(
    'INSERT INTO scores (school_id, term_id, student_id, subject_id, component_id, score) VALUES (?,?,?,?,?,?)', [$A, $termA, $studA, $subjA, $compA, 50]), false);
$expect('duplicate score cell is REJECTED (one score per student/subject/component/term)', fn() => DB::insert(
    'INSERT INTO scores (school_id, term_id, student_id, subject_id, component_id, score) VALUES (?,?,?,?,?,?)', [$A, $termA, $studA, $subjA, $compA, 55]), true);
$expect('negative score is REJECTED by CHECK', fn() => DB::insert(
    'INSERT INTO scores (school_id, term_id, student_id, subject_id, component_id, score) VALUES (?,?,?,?,?,?)', [$A, $termA, $studA, $subjA, $compA, -1]), true);

$expect('a school-less teacher is REJECTED by CHECK', fn() => DB::insert(
    "INSERT INTO users (school_id, name, email, password_hash, role) VALUES (NULL, 'x', 'x@test.local', 'h', 'teacher')"), true);
$expect('a super_admin tied to a school is REJECTED by CHECK', fn() => DB::insert(
    "INSERT INTO users (school_id, name, email, password_hash, role) VALUES (?, 'x', 'y@test.local', 'h', 'super_admin')", [$A]), true);

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
