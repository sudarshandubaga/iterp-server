<?php

declare(strict_types=1);

namespace Iterp\Core;

use Closure;
use RuntimeException;

/**
 * Minimal front-controller router.
 *
 * Supports:
 *  - {param} placeholders
 *  - Closures and [Controller::class, 'method'] handlers
 *  - CORS preflight + auth middleware (callable) per route
 */
class Router
{
    protected array $routes = [];

    public function get(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    private function addRoute(string $method, string $pattern, $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();

        // CORS preflight
        if ($method === 'OPTIONS') {
            (new Response(204))->send();
            return;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->pathMatches($route['pattern'], $path, $params)) {
                $response = $this->run($route, $request, $params);
                if ($response !== null) {
                    $response->send();
                    return;
                }
                return;
            }
        }

        Response::json(404, [
            'success' => false,
            'message' => 'Route not found.',
            'path'    => $path,
        ])->send();
    }

    private function pathMatches(string $pattern, string $path, ?array &$params): bool
    {
        $params = [];
        $regex  = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex  = '#^' . $regex . '/?$#';

        if (preg_match($regex, $path, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return true;
        }

        return false;
    }

    private function run(array $route, Request $request, array $params): ?Response
    {
        // Middleware chain.
        $context = ['user' => null];

        foreach ($route['middleware'] as $middleware) {
            // Direct invocation preserves by-reference param hints on $context.
            $result = $middleware($request, $context);
            if ($result instanceof Response) {
                return $result; // short-circuit (e.g. unauthorised)
            }
        }

        $handler = $route['handler'];
        return $this->resolveHandler($handler)($request, $context, $params);
    }

    private function resolveHandler($handler): callable
    {
        if ($handler instanceof Closure) {
            return $handler;
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;
            if (!class_exists($class)) {
                throw new RuntimeException("Handler class not found: {$class}");
            }
            return function (Request $request, array $context, array $params) use ($class, $method) {
                $controller = new $class();
                return $controller->{$method}($request, $context, $params);
            };
        }

        throw new RuntimeException('Invalid route handler.');
    }
}