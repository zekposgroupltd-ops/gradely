<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});
use App\Services\Barcode128;
use App\Services\ResultVerification;
$svg = Barcode128::svg('/verify/0123456789abcdef0123456789abcdef');
if (!str_starts_with($svg, '<svg') || !str_contains($svg, '<rect') || !str_contains($svg, 'viewBox')) {
    fwrite(STDERR, "FAIL Code128 SVG generation\n"); exit(1);
}
if (Barcode128::svg("bad\nvalue") !== '') {
    fwrite(STDERR, "FAIL Code128 rejects control characters\n"); exit(1);
}
$a = ['school' => ['id' => 1, 'name' => 'School'], 'rows' => [['subject' => 'Math', 'total' => 90.0]]];
$b = ['rows' => [['total' => 90.0, 'subject' => 'Math']], 'school' => ['name' => 'School', 'id' => 1], 'verification' => ['token' => 'not-hashed']];
if (ResultVerification::snapshot($a) !== ResultVerification::snapshot($b)) {
    fwrite(STDERR, "FAIL stable report snapshot hash\n"); exit(1);
}
$b['rows'][0]['total'] = 91.0;
if (ResultVerification::snapshot($a) === ResultVerification::snapshot($b)) {
    fwrite(STDERR, "FAIL changed report snapshot hash\n"); exit(1);
}
echo "3 passed, 0 failed\n";
