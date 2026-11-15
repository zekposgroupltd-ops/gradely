<?php
declare(strict_types=1);

/**
 * Builds a throw-away SQLite database from database/schema.sql (MySQL dialect) and seeds
 * test tenants. TEST USE ONLY - production runs on MySQL/MariaDB.
 *
 * usage: php tests/tools/make_test_db.php /path/to/test.sqlite
 */

use App\Core\{Auth, DB};

$dbFile = $argv[1] ?? null;
if ($dbFile === null) {
    fwrite(STDERR, "usage: make_test_db.php <sqlite-file>\n");
    exit(1);
}
@unlink($dbFile);

define('BASE_PATH', dirname(__DIR__, 2));
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $dbFile);
require BASE_PATH . '/app/bootstrap.php';

function mysql_to_sqlite(string $sql): string
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/SET NAMES[^;]*;/i', '', $sql);
    $sql = preg_replace('/\)\s*ENGINE=[^;]*;/i', ');', $sql);
    $sql = preg_replace('/(?:BIG)?INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\b(?:TINY|SMALL|BIG)?INT(?:\(\d+\))?\s+UNSIGNED/i', 'INTEGER', $sql);
    $sql = preg_replace('/ENUM\s*\([^)]*\)/i', 'TEXT', $sql);
    $sql = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
    $sql = preg_replace('/^\s*UNIQUE KEY\s+\w+\s*\(/mi', '  UNIQUE (', $sql);
    $sql = preg_replace('/^\s*KEY\s+\w+\s*\([^)]*\),?\s*$/mi', '', $sql);
    $sql = preg_replace('/,(\s*\))/', '$1', $sql);
    return $sql;
}

$pdo = DB::pdo();
$pdo->exec(mysql_to_sqlite((string) file_get_contents(BASE_PATH . '/database/schema.sql')));

$pw = static fn(string $p): string => Auth::hash($p);

DB::insert("INSERT INTO users (school_id, name, email, password_hash, role) VALUES (NULL, 'Platform Owner', 'super@test.local', ?, 'super_admin')", [$pw('SuperPass#12345')]);

$schools = [
    ['Alpha Academy', 'alpha', 'active', 'a'],
    ['Beta College', 'beta', 'active', 'b'],
    ['Gamma School', 'gamma', 'suspended', 'c'],
];
foreach ($schools as [$name, $slug, $status, $k]) {
    $sid = DB::insert('INSERT INTO schools (name, slug, status, email) VALUES (?,?,?,?)', [$name, $slug, $status, "info@$slug.test"]);
    DB::insert('INSERT INTO school_settings (school_id) VALUES (?)', [$sid]);
    DB::insert("INSERT INTO users (school_id, name, email, password_hash, role) VALUES (?,?,?,?, 'school_admin')", [$sid, "$name Admin", "$k-admin@test.local", $pw('AdminPass#12345')]);
    if ($k === 'a') {
        $tid = DB::insert("INSERT INTO users (school_id, name, email, password_hash, role) VALUES (?,?,?,?, 'teacher')", [$sid, 'Alpha Teacher', 'a-teacher@test.local', $pw('TeacherPass#12345')]);
        DB::insert("INSERT INTO user_permissions (user_id, permission) VALUES (?, 'score.enter')", [$tid]);
    }
}
echo "test db ready: $dbFile\n";
