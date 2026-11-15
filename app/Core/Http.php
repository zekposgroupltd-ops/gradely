<?php
declare(strict_types=1);

namespace App\Core;

final class Http
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Request path with the optional APP_BASE_PATH prefix removed. */
    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return Env::bool('TRUST_PROXY') && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (Env::bool('TRUST_PROXY') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
            }
        }
        return $ip;
    }

    public static function input(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function redirect(string $path): never
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target, true, 302);
        exit;
    }

    public static function abort(int $code, string $message = ''): never
    {
        $titles = [
            403 => 'Access denied', 404 => 'Page not found', 405 => 'Method not allowed',
            419 => 'Session expired', 429 => 'Too many requests', 500 => 'Something went wrong',
        ];
        http_response_code($code);
        View::render('errors/error', [
            'title' => $titles[$code] ?? 'Error',
            'code' => $code,
            'message' => $message,
        ], 'auth');
        exit;
    }
}
