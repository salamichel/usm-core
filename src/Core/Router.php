<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        // Strip query string and decode
        $uri = strtok($uri, '?');
        $uri = '/' . trim(rawurldecode($uri), '/');

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            $regex  = $this->compile($pattern);
            if (!preg_match($regex, $uri, $matches)) {
                continue;
            }

            // Named capture groups become params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->call($handler, $params);
            return;
        }

        http_response_code(404);
        // Try to render a 404 template, fall back to plain text
        try {
            View::render('404.twig');
        } catch (\Throwable) {
            echo '<h1>404 — Page introuvable</h1>';
        }
    }

    private function compile(string $pattern): string
    {
        // Convert {param} to named capture groups (?P<param>[^/]+)
        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#u';
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            (new $class())->$method($params);
        } else {
            $handler($params);
        }
    }
}
