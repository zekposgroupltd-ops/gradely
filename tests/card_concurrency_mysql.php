<?php
declare(strict_types=1);
/**
 * Concurrency proof for REAL MySQL/MariaDB (cannot run in the SQLite sandbox).
 * Use a THROW-AWAY database that already has database/schema.sql imported and ONE school with a term,
 * a student ADM002 and a published result for it. Never point this at production.
 *
 *   php tests/card_concurrency_mysql.php <school_id> <term_id> [workers=30]
 *
 * Reads DB_* from .env. Creates one card, fires N parallel PHP processes at it, and checks that exactly
 * 5 succeed, use_count = 5 and 5 usage rows exist. Exit code 0 = pass.
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\ScratchCardService as C;

[$sid, $term] = [(int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0)];
$n = (int) ($argv[3] ?? 30);
if (!$sid || !$term) { fwrite(STDERR, "usage: php tests/card_concurrency_mysql.php <school_id> <term_id> [workers]\n"); exit(2); }
if (!DB::isMysql()) { fwrite(STDERR, "This script is for MySQL/MariaDB; set DB_DRIVER=mysql in .env\n"); exit(2); }

$card = C::generateBatch($sid, $term, 1, 'concurrency-check', null, null)['cards'][0];
$code = <<<'PHP'
define('BASE_PATH', $argv[1]); require BASE_PATH . '/app/bootstrap.php';
usleep(random_int(0, 2000));
$r = App\Services\ScratchCardService::redeem((int)$argv[2], (int)$argv[3], 'ADM002', $argv[4], '198.51.100.9', 'worker');
echo $r['ok'] ? 'OK' : 'NO';
PHP;
$procs = []; $pipes = [];
for ($i = 0; $i < $n; $i++) {
    $procs[$i] = proc_open([PHP_BINARY, '-r', $code, BASE_PATH, (string) $sid, (string) $term, $card['pin']], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$wins = 0; $bad = 0;
foreach ($procs as $i => $p) {
    $o = trim(stream_get_contents($pipes[$i][1])); stream_get_contents($pipes[$i][2]); proc_close($p);
    if ($o === 'OK') { $wins++; } elseif ($o !== 'NO') { $bad++; }
}
$uses = (int) DB::val('SELECT use_count FROM scratch_cards WHERE serial = ?', [$card['serial']]);
$rows = (int) DB::val('SELECT COUNT(*) FROM scratch_card_usages WHERE card_id = (SELECT id FROM scratch_cards WHERE serial = ?)', [$card['serial']]);
printf("workers=%d successes=%d errors=%d use_count=%d usage_rows=%d\n", $n, $wins, $bad, $uses, $rows);
$ok = $wins === 5 && $uses === 5 && $rows === 5 && $bad === 0;
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
