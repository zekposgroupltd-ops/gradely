<?php
declare(strict_types=1);
/** Scratch card tests. usage: php tests/cards_test.php <scratch-dir> */
require __DIR__ . '/tools/bootstrap_test.php';
$tmp = rtrim($argv[1] ?? sys_get_temp_dir(), '/');
test_boot($tmp . '/cards.sqlite');

use App\Core\DB;
use App\Services\ScratchCardService as C;

$pass = $fail = 0;
$t = static function (string $label, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { $pass++; echo "PASS  $label\n"; } else { $fail++; echo "FAIL  $label $detail\n"; }
};

$A = seed_school('Alpha Academy', 3, ['English']);
$B = seed_school('Beta College', 2, ['English']);
// publish Alpha students 1 and 2 (student 3 stays unpublished); publish Beta student 1
DB::run("INSERT INTO result_summaries (school_id, term_id, student_id, class_id, subjects_count, total, average, position, class_size, status) VALUES (?,?,?,?,1,80,80,1,3,'published')", [$A['sid'], $A['term'], $A['students'][0], $A['class']]);
DB::run("INSERT INTO result_summaries (school_id, term_id, student_id, class_id, subjects_count, total, average, position, class_size, status) VALUES (?,?,?,?,1,70,70,2,3,'published')", [$A['sid'], $A['term'], $A['students'][1], $A['class']]);
DB::run("INSERT INTO result_summaries (school_id, term_id, student_id, class_id, subjects_count, total, average, position, class_size, status) VALUES (?,?,?,?,1,60,60,3,3,'draft')", [$A['sid'], $A['term'], $A['students'][2], $A['class']]);
DB::run("INSERT INTO result_summaries (school_id, term_id, student_id, class_id, subjects_count, total, average, position, class_size, status) VALUES (?,?,?,?,1,50,50,1,2,'published')", [$B['sid'], $B['term'], $B['students'][0], $B['class']]);
// a second term in Alpha
$term2 = DB::insert("INSERT INTO terms (school_id, session_id, name) VALUES (?,?,'Second Term')", [$A['sid'], $A['session']]);

$batch = C::generateBatch($A['sid'], $A['term'], 6, 'Test batch', null, null);
$cards = $batch['cards'];
$t('batch has 6 cards', count($cards) === 6);
$t('serials are unique', count(array_unique(array_column($cards, 'serial'))) === 6);
$t('PINs are unique 12-digit numbers', count(array_unique(array_column($cards, 'pin'))) === 6 && !array_filter($cards, fn($c) => !preg_match('/^\d{12}$/', $c['pin'])));
$stored = DB::all('SELECT pin_hash FROM scratch_cards WHERE school_id = ?', [$A['sid']]);
$t('database holds only hashes, never plaintext PINs', !array_intersect(array_column($stored, 'pin_hash'), array_column($cards, 'pin')) && strlen($stored[0]['pin_hash']) === 64);
$leak = DB::all('SELECT * FROM scratch_cards');
$t('no column contains a plaintext PIN', !array_filter($leak, function ($row) use ($cards) { foreach ($cards as $c) { if (in_array($c['pin'], array_map('strval', $row), true)) { return true; } } return false; }));

$adm = 'ADM001'; $ip = '203.0.113.5'; $ua = 'phpunit';
$use = fn(array $S, string $adm, string $pin, ?int $term = null) => C::redeem($S['sid'], $term ?? $S['term'], $adm, $pin, $ip, $ua);
$count = fn(int $i) => (int) DB::val('SELECT use_count FROM scratch_cards WHERE serial = ?', [$cards[$i]['serial']]);

// ---- five uses, sixth rejected
$p = $cards[0]['pin'];
for ($i = 1; $i <= 5; $i++) {
    $r = $use($A, $adm, $p);
    $t("use #$i succeeds", $r['ok'] === true, json_encode($r));
}
$t('remaining reaches 0 after 5th', ($r['remaining'] ?? -1) === 0);
$r = $use($A, $adm, $p);
$t('6th use rejected as exhausted', $r['ok'] === false && $r['code'] === 'exhausted');
$t('use_count stays at 5', $count(0) === 5);
$t('card marked exhausted', DB::val('SELECT status FROM scratch_cards WHERE serial = ?', [$cards[0]['serial']]) === 'exhausted');
$t('exactly 5 usage rows logged', (int) DB::val('SELECT COUNT(*) FROM scratch_card_usages WHERE card_id = (SELECT id FROM scratch_cards WHERE serial = ?)', [$cards[0]['serial']]) === 5);

// ---- failures never consume
$p = $cards[1]['pin'];
$r = $use($A, $adm, '000000000000');       $t('unknown PIN rejected', !$r['ok'] && $r['code'] === 'invalid_card');
$r = $use($A, $adm, '123');                $t('malformed PIN rejected', !$r['ok']);
$r = $use($A, $adm, $p, $term2);           $t('wrong TERM rejected', !$r['ok'] && $r['code'] === 'wrong_scope');
$r = $use($B, 'ADM001', $p);               $t('wrong SCHOOL rejected', !$r['ok'] && $r['code'] === 'wrong_scope');
$r = $use($A, 'NOPE999', $p);              $t('unknown student rejected', !$r['ok'] && $r['code'] === 'student_not_found');
$r = $use($A, 'ADM003', $p);               $t('unpublished result rejected', !$r['ok'] && $r['code'] === 'not_published');
$t('none of those failures consumed a use', $count(1) === 0);
$t('no usage rows for failures', (int) DB::val('SELECT COUNT(*) FROM scratch_card_usages WHERE card_id = (SELECT id FROM scratch_cards WHERE serial = ?)', [$cards[1]['serial']]) === 0);
$r = $use($A, 'adm001', $p);               $t('admission number is case-insensitive', $r['ok'] === true);
$t('success consumed exactly one use', $count(1) === 1);

// ---- first-student binding
$r = $use($A, 'ADM002', $p);
$t('card bound to first student: a different student is rejected', !$r['ok'] && $r['code'] === 'wrong_student');
$t('rejected binding attempt did not consume', $count(1) === 1);
DB::run("UPDATE school_settings SET card_binding='none' WHERE school_id=?", [$A['sid']]);
$r = $use($A, 'ADM002', $p);
$t('with binding off, another student may use it', $r['ok'] === true && $count(1) === 2);
DB::run("UPDATE school_settings SET card_binding='first_student' WHERE school_id=?", [$A['sid']]);

// ---- revoked / expired
DB::run("UPDATE scratch_cards SET status='revoked' WHERE serial=?", [$cards[2]['serial']]);
$r = $use($A, $adm, $cards[2]['pin']);     $t('revoked card rejected', !$r['ok'] && $r['code'] === 'revoked');
DB::run("UPDATE scratch_cards SET expires_at='2000-01-01 00:00:00' WHERE serial=?", [$cards[3]['serial']]);
$r = $use($A, $adm, $cards[3]['pin']);     $t('expired card rejected', !$r['ok'] && $r['code'] === 'expired');
$t('revoked/expired not consumed', $count(2) === 0 && $count(3) === 0);

// ---- PIN formatting tolerated
$fmt = substr($cards[4]['pin'], 0, 4) . '-' . substr($cards[4]['pin'], 4, 4) . ' ' . substr($cards[4]['pin'], 8);
$r = $use($A, $adm, $fmt);                 $t('PIN with dashes/spaces accepted', $r['ok'] === true);

// ---- stats
$st = C::stats($A['sid'], $A['term']);
$t('stats total = 6', ($st['total'] ?? 0) === 6, json_encode($st));
$t('stats exhausted = 1, revoked = 1, expired = 1', ($st['exhausted'] ?? -1) === 1 && ($st['revoked'] ?? -1) === 1 && ($st['expired'] ?? -1) === 1, json_encode($st));

// ---- concurrency: 12 parallel PROCESSES race for the 5 uses of one fresh card
$race = C::generateBatch($A['sid'], $A['term'], 1, 'race', null, null)['cards'][0];
$worker = __DIR__ . '/tools/card_worker.php';
$procs = [];
for ($i = 0; $i < 12; $i++) {
    $procs[] = proc_open([PHP_BINARY, $worker, $tmp . '/cards.sqlite', (string) $A['sid'], (string) $A['term'], $race['pin']], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$wins = 0; $errs = [];
foreach ($procs as $i => $pr) {
    $out = trim(stream_get_contents($pipes[$i][1])); $err = trim(stream_get_contents($pipes[$i][2]));
    proc_close($pr);
    if ($out === 'OK') { $wins++; } elseif ($out !== 'NO') { $errs[] = $out . $err; }
}
$final = (int) DB::val('SELECT use_count FROM scratch_cards WHERE serial = ?', [$race['serial']]);
$logged = (int) DB::val('SELECT COUNT(*) FROM scratch_card_usages WHERE card_id = (SELECT id FROM scratch_cards WHERE serial = ?)', [$race['serial']]);
$t('parallel race: exactly 5 of 12 succeed', $wins === 5, "wins=$wins errors=" . json_encode($errs));
$t('parallel race: no worker crashed or hit a lock error', !$errs, json_encode($errs));
$t('parallel race: use_count is 5, never more', $final === 5, "final=$final");
$t('parallel race: usage log has exactly 5 rows', $logged === 5, "logged=$logged");

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
