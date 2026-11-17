<?php
declare(strict_types=1);

use App\Core\{Env, Http, Session};

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Lagos'));

$debug = Env::get('APP_ENV', 'production') !== 'production';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
ini_set('error_log', $logDir . '/php-error.log');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><p style="font-family:sans-serif;padding:2rem">Something went wrong. Please try again shortly.</p>';
});

if (PHP_SAPI !== 'cli') {
    // Optional app-level HTTPS redirect (FORCE_HTTPS=1). Target host comes from APP_URL, never from the request.
    if (Env::bool('FORCE_HTTPS') && !Http::isHttps()) {
        $host = parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST);
        if ($host) {
            header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    if (Http::isHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    Session::start();
}
