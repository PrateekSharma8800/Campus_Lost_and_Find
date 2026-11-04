<?php
/**
 * Minimal router: exact paths plus {param} placeholders, per-route
 * middleware, automatic CSRF verification on every POST.
 */

declare(strict_types=1);

final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:array, middleware:array}> */
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path   = current_path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            if ($method === 'POST') {
                $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                if (!Csrf::verify(is_string($token) ? $token : null)) {
                    abort(419);
                }
            }

            foreach ($route['middleware'] as $mw) {
                $this->runMiddleware($mw, $method, $path);
            }

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }

        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            abort(405);
        }
        abort(404);
    }

    private function runMiddleware(string $name, string $method, string $path): void
    {
        switch ($name) {
            case 'guest':
                if (Auth::check()) {
                    redirect('/dashboard');
                }
                break;

            case 'auth':
            case 'admin':
                if (!Auth::check()) {
                    if ($method === 'GET') {
                        Session::set('intended', $_SERVER['REQUEST_URI'] ?? $path);
                    }
                    Session::flash('warning', 'Please log in to continue.');
                    redirect('/login');
                }
                if ($name === 'admin' && !Auth::isAdmin()) {
                    abort(403);
                }
                break;

            default:
                throw new LogicException("Unknown middleware: $name");
        }
    }
}
