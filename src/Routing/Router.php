<?php

declare(strict_types=1);

namespace App\Routing;

final class Router
{
    /**
     * @param list<CompiledRoute> $routes
     */
    public function __construct(private readonly array $routes)
    {
    }

    public function match(string $method, string $path): MatchResult
    {
        $method = strtoupper($method);
        $effectiveMethod = $method === 'HEAD' ? 'GET' : $method;

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $allowedForPath = [];
        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $path, $matches) !== 1) {
                continue;
            }

            foreach ($route->methods as $candidate) {
                $allowedForPath[$candidate] = true;
                if ($candidate === 'GET') {
                    $allowedForPath['HEAD'] = true;
                }
            }

            if (!in_array($effectiveMethod, $route->methods, true)) {
                continue;
            }

            $params = [];
            foreach ($route->paramNames as $name) {
                $params[$name] = (string)$matches[$name];
            }

            return new MatchResult(
                controller: $route->controller,
                action: $route->action,
                params: $params,
            );
        }

        if ($allowedForPath !== []) {
            $allowed = array_keys($allowedForPath);
            sort($allowed);
            throw new MethodNotAllowedException(
                allowedMethods: $allowed,
                message: "Method {$method} not allowed for {$path}",
            );
        }

        throw new RouteNotFoundException("No route for {$method} {$path}");
    }
}
