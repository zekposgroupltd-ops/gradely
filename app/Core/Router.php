<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:array,mw:array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array $handler, array $mw): void
    {
        // {id} matches digits only; any other {name} matches [A-Za-z0-9_-]+
        $regex = '#^' . preg_replace_callback(
            '/\{(\w+)\}/',
            static fn(array $m) => '(?P<' . $m[1] . '>' . ($m[1] === 'id' || str_ends_with($m[1], '_id') ? '[0-9]+' : '[A-Za-z0-9_-]+') . ')',
            $pattern
        ) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'mw' => $mw];
    }

    public function dispatch(string $method, string $path): void
    {
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($r['method'] !== $method) {
                continue;
            }
            if ($method === 'POST' && !Csrf::verify()) {
                Http::abort(419, 'Your session expired or the form was invalid. Please go back, refresh the page and try again.');
            }
            foreach ($r['mw'] as $mw) {
                $mw();
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = ctype_digit($v) && ($k === 'id' || str_ends_with($k, '_id')) ? (int) $v : $v;
                }
            }
            [$class, $action] = $r['handler'];
            (new $class())->$action(...$params);
            return;
        }
        Http::abort($pathMatched ? 405 : 404);
    }
}
