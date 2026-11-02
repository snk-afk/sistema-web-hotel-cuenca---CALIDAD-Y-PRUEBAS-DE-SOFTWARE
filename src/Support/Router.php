<?php

declare(strict_types=1);

namespace App\Support;

final class Router
{
    private ?AuthMiddleware $auth_middleware = null;

    /**
     * @var array<string, array<string, callable|array{0: class-string, 1: string}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $raw_path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($raw_path) && $raw_path !== '' ? $raw_path : '/';

        // Strip /public prefix if hostinger rewrite passes /public/route
        if (str_starts_with($path, '/public/')) {
            $path = substr($path, 7);
        } elseif ($path === '/public') {
            $path = '/';
        }

        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $matched_route = $this->matchRoute($method, $path);
        if ($matched_route !== null) {
            [$route_path, $handler, $parameters] = $matched_route;

            // Security middleware: auth + RBAC + CSRF
            if ($this->auth_middleware !== null) {
                try {
                    $this->auth_middleware->check($method, $path);
                } catch (AuthException $e) {
                    if ($e->redirect_location !== null) {
                        header('Location: ' . $e->redirect_location, true, $e->http_status);
                        return null;
                    }

                    http_response_code($e->http_status);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ]);
                    return null;
                }
            }

            if (is_array($handler)) {
                [$class, $action] = $handler;
                /** @var object $controller */
                $controller = new $class();
                /** @var callable $callable */
                $callable = [$controller, $action];
                return call_user_func_array($callable, array_values($parameters));
            }

            return call_user_func_array($handler, array_values($parameters));
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found', 'status' => 404]);
        return null;
    }

    public function setAuthMiddleware(?AuthMiddleware $middleware): void
    {
        $this->auth_middleware = $middleware;
    }

    /**
     * @return array{0: string, 1: callable|array{0: class-string, 1: string}, 2: array<string, int|string>}|null
     */
    private function matchRoute(string $method, string $path): ?array
    {
        if (isset($this->routes[$method][$path])) {
            return [$path, $this->routes[$method][$path], []];
        }

        foreach ($this->routes[$method] as $route_path => $handler) {
            $parameters = $this->matchTemplate($route_path, $path);
            if ($parameters !== null) {
                return [$route_path, $handler, $parameters];
            }
        }

        return null;
    }

    /**
     * @return array<string, int|string>|null
     */
    private function matchTemplate(string $template, string $path): ?array
    {
        $template_segments = $template === '/' ? [] : explode('/', trim($template, '/'));
        $path_segments = $path === '/' ? [] : explode('/', trim($path, '/'));

        if (count($template_segments) !== count($path_segments)) {
            return null;
        }

        $parameters = [];
        foreach ($template_segments as $index => $template_segment) {
            $path_segment = rawurldecode($path_segments[$index]);
            if (str_contains($path_segment, '/')) {
                return null;
            }

            if (str_starts_with($template_segment, '{') && str_ends_with($template_segment, '}')) {
                $name = substr($template_segment, 1, -1);
                if ($name === '') {
                    return null;
                }

                if (($name === 'id' || str_ends_with($name, '_id')) && !ctype_digit($path_segment)) {
                    return null;
                }

                $parameters[$name] = $name === 'id' || str_ends_with($name, '_id')
                    ? (int) $path_segment
                    : $path_segment;
                continue;
            }

            if ($template_segment !== $path_segment) {
                return null;
            }
        }

        return $parameters;
    }
}
