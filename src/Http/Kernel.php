<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Attributes\RequiresAuth;
use App\Auth\Attributes\RequiresJwt;
use App\Auth\Attributes\RequiresRole;
use App\Auth\Middleware\AuthJwtMiddleware;
use App\Auth\Middleware\RequireAuthMiddleware;
use App\Auth\Middleware\RequireRoleMiddleware;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Middleware\Pipeline;
use App\Routing\MethodNotAllowedException;
use App\Routing\RouteNotFoundException;
use App\Routing\Router;
use App\Support\Container;
use App\Support\Logger\LoggerInterface;
use App\Users\User;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final class Kernel
{
    /**
     * @param list<MiddlewareInterface> $globalMiddleware
     */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly array $globalMiddleware = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            $this->logger?->error('unhandled_exception', [
                'method' => $request->method,
                'path' => $request->path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'user_id' => $request->attribute('user') instanceof User
                    ? $request->attribute('user')->id
                    : null,
            ]);
            return $this->errorResponse($request, $e);
        }
    }

    private function dispatch(Request $request): Response
    {
        try {
            $match = $this->router->match($request->method, $request->path);
        } catch (RouteNotFoundException) {
            return $this->notFoundResponse($request);
        } catch (MethodNotAllowedException $e) {
            return $this->methodNotAllowedResponse($request, $e->allowedMethods);
        }

        $controller = $this->container->get($match->controller);
        $actionMethod = new ReflectionMethod($controller, $match->action);
        $routeMiddleware = $this->routeMiddlewareFor($actionMethod);

        $finalHandler = function (Request $req) use ($controller, $actionMethod, $match): Response {
            $args = $this->bindActionArgs($actionMethod, $req, $match->params);
            /** @var Response $response */
            $response = $controller->{$match->action}(...$args);
            return $response;
        };

        return Pipeline::run(
            [...$this->globalMiddleware, ...$routeMiddleware],
            $finalHandler,
            $request,
        );
    }

    private function notFoundResponse(Request $request): Response
    {
        if (str_starts_with($request->path, '/api/')) {
            return JsonEnvelope::error('not_found', 'Route not found.', 404);
        }
        return Response::html('<!doctype html><h1>404 Not Found</h1>', 404);
    }

    /**
     * @param list<string> $allowed
     */
    private function methodNotAllowedResponse(Request $request, array $allowed): Response
    {
        $allowHeader = implode(', ', $allowed);
        if (str_starts_with($request->path, '/api/')) {
            $response = JsonEnvelope::error('method_not_allowed', 'Method not allowed.', 405);
            return new Response(
                status: 405,
                headers: array_merge($response->headers, ['allow' => $allowHeader]),
                body: $response->body,
            );
        }
        return new Response(
            status: 405,
            headers: ['allow' => $allowHeader],
            body: '<!doctype html><h1>405 Method Not Allowed</h1>',
        );
    }

    private function errorResponse(Request $request, Throwable $e): Response
    {
        $isApi = str_starts_with($request->path, '/api/');

        if ($isApi) {
            $extra = $this->debug
                ? ['exception' => $e::class, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                : null;
            return JsonEnvelope::error(
                code: 'internal_error',
                message: $this->debug ? $e->getMessage() : 'Something went wrong.',
                status: 500,
                extra: $extra,
            );
        }

        if ($this->debug) {
            $body = '<!doctype html><h1>500 Internal Server Error</h1>'
                . '<p><strong>' . htmlspecialchars($e::class, ENT_QUOTES) . ':</strong> '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
                . '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';
            return Response::html($body, 500);
        }

        return Response::html('<!doctype html><h1>500 Internal Server Error</h1>', 500);
    }

    /**
     * @return list<MiddlewareInterface>
     */
    private function routeMiddlewareFor(ReflectionMethod $action): array
    {
        $stack = [];
        // RequiresAuth → RequiresJwt → RequiresRole. Auth gates run before role checks.
        foreach ($action->getAttributes(RequiresAuth::class) as $_) {
            $stack[] = new RequireAuthMiddleware();
        }
        foreach ($action->getAttributes(RequiresJwt::class) as $_) {
            $stack[] = $this->container->get(AuthJwtMiddleware::class);
        }
        foreach ($action->getAttributes(RequiresRole::class) as $attr) {
            /** @var RequiresRole $instance */
            $instance = $attr->newInstance();
            $stack[] = new RequireRoleMiddleware($instance->role);
        }
        return $stack;
    }

    /**
     * @param array<string, string> $pathParams
     * @return list<mixed>
     */
    private function bindActionArgs(ReflectionMethod $method, Request $request, array $pathParams): array
    {
        $args = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && $type->getName() === Request::class) {
                $args[] = $request;
                continue;
            }

            $name = $parameter->getName();
            if (array_key_exists($name, $pathParams)) {
                $args[] = $this->coerce($pathParams[$name], $type);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            $args[] = null;
        }

        return $args;
    }

    private function coerce(string $raw, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType) {
            return $raw;
        }
        return match ($type->getName()) {
            'int' => (int)$raw,
            'float' => (float)$raw,
            'bool' => $raw === '1' || strtolower($raw) === 'true',
            default => $raw,
        };
    }
}
