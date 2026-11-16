<?php
declare(strict_types=1);
// Shared by CLI tests: builds a fresh SQLite copy of the schema and boots the app against it.
use App\Core\DB;

function test_boot(string $dbFile, bool $rebuild = true): void
{
    if ($rebuild) {
        @unlink($dbFile);
        passthru('php ' . escapeshellarg(__DIR__ . '/make_test_db.php') . ' ' . escapeshellarg($dbFile) . ' >/dev/null', $rc);
        if ($rc !== 0) { fwrite(STDERR, "cannot build test db\n"); exit(2); }
    }
    if (!defined('BASE_PATH')) { define('BASE_PATH', dirname(__DIR__, 2)); }
    putenv('DB_DRIVER=sqlite');
    putenv('DB_SQLITE_PATH=' . $dbFile);
    putenv('APP_SECRET=test-secret-test-secret-test-secret-123');
    require_once BASE_PATH . '/app/bootstrap.php';
}

/** A school with 1 session/term, 1 class, N subjects, N students. Returns ids. */
function seed_school(string $name, int $students, array $subjects, string $termName = 'First Term'): array
{
    $sid = App\Services\SchoolProvisioner::create(
        ['name' => $name, 'email' => null, 'phone' => null, 'address' => null],
        ['name' => 'Admin', 'email' => strtolower(preg_replace('/\W/', '', $name)) . '@t.local', 'password' => 'Passw0rd!Passw0rd']
    );
    $sess = DB::insert('INSERT INTO academic_sessions (school_id, name, is_current) VALUES (?,?,1)', [$sid, '2026/2027']);
    $term = DB::insert('INSERT INTO terms (school_id, session_id, name, is_current) VALUES (?,?,?,1)', [$sid, $sess, $termName]);
    $class = DB::insert('INSERT INTO classes (school_id, name, level_order) VALUES (?,?,1)', [$sid, 'JSS 1']);
    $subIds = [];
    foreach ($subjects as $s) {
        $id = DB::insert('INSERT INTO subjects (school_id, name) VALUES (?,?)', [$sid, $s]);
        DB::insert('INSERT INTO class_subjects (school_id, class_id, subject_id) VALUES (?,?,?)', [$sid, $class, $id]);
        $subIds[] = $id;
    }
    $stu = [];
    for ($i = 1; $i <= $students; $i++) {
        $stu[] = DB::insert('INSERT INTO students (school_id, admission_no, first_name, last_name, class_id) VALUES (?,?,?,?,?)', [$sid, sprintf('ADM%03d', $i), "First$i", "Last$i", $class]);
    }
    $comps = array_map('intval', array_column(DB::all('SELECT id FROM assessment_components WHERE school_id = ? ORDER BY sort_order', [$sid]), 'id'));
    return ['sid' => $sid, 'session' => $sess, 'term' => $term, 'class' => $class, 'subjects' => $subIds, 'students' => $stu, 'comps' => $comps];
}

function publish_all(int $sid, int $termId): void
{
    DB::run("UPDATE result_summaries SET status = 'published' WHERE school_id = ? AND term_id = ?", [$sid, $termId]);
}
