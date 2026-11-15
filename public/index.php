<?php
declare(strict_types=1);

// PHP's built-in server (local dev/tests): let it serve real static files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

// The app folder normally sits one level above public/. On hosts where it lives elsewhere,
// set GRADELY_BASE (environment variable) to its absolute path.
define('BASE_PATH', getenv('GRADELY_BASE') ?: dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

/** @var App\Core\Router $router */
$router = require BASE_PATH . '/app/routes.php';
$router->dispatch(App\Core\Http::method(), App\Core\Http::path());
