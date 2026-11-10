<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router: method + path pattern ("/admin/members/{id}") -> [Controller, method].
 * Middleware are callables run before the handler.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $mw = []): void
    {
        $this->add('GET', $path, $handler, $mw);
    }

    public function post(string $path, array $handler, array $mw = []): void
    {
        $this->add('POST', $path, $handler, $mw);
    }

    public function add(string $method, string $path, array $handler, array $mw = []): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'mw');
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        $allowed = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            $allowed = true;
            if ($r['method'] !== $method) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST') {
                Csrf::verify();
            }
            foreach ($r['mw'] as $mw) {
                $mw();
            }
            [$class, $fn] = $r['handler'];
            (new $class())->$fn(...$params);
            return;
        }
        throw new HttpException($allowed ? 405 : 404);
    }
}
