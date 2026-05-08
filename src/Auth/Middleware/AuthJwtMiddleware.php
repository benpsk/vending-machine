<?php

declare(strict_types=1);

namespace App\Auth\Middleware;

use App\Auth\Exceptions\JwtVerificationException;
use App\Auth\JwtAuthenticator;
use App\Database\Mysql\UserRepository;
use App\Http\JsonEnvelope;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

final class AuthJwtMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtAuthenticator $jwt,
        private readonly UserRepository $users,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return $this->unauthorized('Missing bearer token.');
        }

        try {
            $claims = $this->jwt->verify($token);
        } catch (JwtVerificationException $e) {
            return $this->unauthorized('Invalid token: ' . $e->failure->value);
        }

        $user = $this->users->findById($claims->sub);
        if ($user === null) {
            return $this->unauthorized('Token subject is no longer a valid user.');
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = JsonEnvelope::error('invalid_token', $message, 401);
        return new Response(
            status: $response->status,
            headers: array_merge($response->headers, ['www-authenticate' => 'Bearer error="invalid_token"']),
            body: $response->body,
        );
    }
}
