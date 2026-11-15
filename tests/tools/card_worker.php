<?php
// Child process for the concurrency test: one redemption attempt. Prints OK / NO.
require __DIR__ . '/bootstrap_test.php';
test_boot($argv[1], false);
usleep(random_int(0, 3000));
try {
    $r = App\Services\ScratchCardService::redeem((int) $argv[2], (int) $argv[3], 'ADM002', $argv[4], '198.51.100.9', 'worker');
    echo $r['ok'] ? 'OK' : 'NO';
} catch (Throwable $e) {
    echo 'ERR ' . $e->getMessage();
}
