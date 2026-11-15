<?php
declare(strict_types=1);
/**
 * Imports database/schema.sql into the configured MySQL database, but ONLY if it is still empty
 * (no `schools` table). Safe to run on every start. usage: php tools/install_schema.php
 * Reads DB_* from the environment or .env. Never drops or alters existing tables.
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\DB;

if (!DB::isMysql()) { fwrite(STDERR, "install_schema: DB_DRIVER must be mysql\n"); exit(2); }
$exists = (int) DB::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schools'");
if ($exists > 0) { echo "install_schema: tables already exist, nothing to do\n"; exit(0); }

$sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);             // line comments
$statements = array_filter(array_map('trim', explode(";\n", $sql)), static fn($s) => $s !== '');
$n = 0;
foreach ($statements as $st) { DB::pdo()->exec($st); $n++; }
echo "install_schema: ran $n statements\n";
