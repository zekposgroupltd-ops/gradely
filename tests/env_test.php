<?php
declare(strict_types=1);
/** DATABASE_URL parsing. usage: php tests/env_test.php */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';
use App\Core\Env;
$pass = $fail = 0;
$t = function (string $l, bool $c, string $d = '') use (&$pass, &$fail) { if ($c) { $pass++; echo "PASS  $l\n"; } else { $fail++; echo "FAIL  $l $d\n"; } };
$u = 'mysql://gradely_user:p%40ss%2Fw0rd@db.internal:3307/gradely_db';
$t('host', Env::fromDatabaseUrl('DB_HOST', $u) === 'db.internal');
$t('port', Env::fromDatabaseUrl('DB_PORT', $u) === '3307');
$t('name', Env::fromDatabaseUrl('DB_NAME', $u) === 'gradely_db');
$t('user', Env::fromDatabaseUrl('DB_USER', $u) === 'gradely_user');
$t('password with @ and / is URL-decoded', Env::fromDatabaseUrl('DB_PASS', $u) === 'p@ss/w0rd');
$t('driver is mysql', Env::fromDatabaseUrl('DB_DRIVER', $u) === 'mysql');
$t('no port in URL -> null (default 3306 applies)', Env::fromDatabaseUrl('DB_PORT', 'mysql://u:p@h/db') === null);
$t('postgres URL is ignored', Env::fromDatabaseUrl('DB_HOST', 'postgres://u:p@h:5432/db') === null);
$t('garbage URL is ignored', Env::fromDatabaseUrl('DB_HOST', 'not a url') === null);
putenv('DATABASE_URL=' . $u); putenv('DB_HOST'); putenv('DB_DRIVER');
$t('Env::get falls back to DATABASE_URL', Env::get('DB_HOST', 'localhost') === 'db.internal' && Env::get('DB_USER', '') === 'gradely_user' && Env::get('DB_DRIVER', 'x') === 'mysql');
putenv('DB_HOST=explicit.host');
$t('explicit DB_HOST wins over DATABASE_URL', Env::get('DB_HOST') === 'explicit.host');
putenv('DB_HOST'); putenv('DATABASE_URL');
$t('without any settings the default is used', Env::get('DB_HOST', 'localhost') === 'localhost');
putenv('DB_SQLITE_PATH=/x.sqlite'); $t('sqlite path untouched', Env::get('DB_SQLITE_PATH') === '/x.sqlite');
echo "\nRESULT: $pass passed, $fail failed\n"; exit($fail ? 1 : 0);
