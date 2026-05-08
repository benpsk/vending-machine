<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Mysql\LoginAttemptRepository;
use App\Database\Mysql\UserRepository;
use App\Http\JsonEnvelope;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Route;
use DateTimeImmutable;

final class AuthApiController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const RATE_WINDOW_SECONDS = 900;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly JwtAuthenticator $jwt,
        private readonly LoginAttemptRepository $attempts,
    ) {
    }

    #[Route('/api/auth/login', methods: ['POST'], name: 'api.auth.login')]
    public function login(Request $request): Response
    {
        $ip = $this->clientIp($request);
        $since = (new DateTimeImmutable())->modify('-' . self::RATE_WINDOW_SECONDS . ' seconds');

        if ($this->attempts->countFailedSince($ip, $since) >= self::MAX_FAILED_ATTEMPTS) {
            $response = JsonEnvelope::error(
                'rate_limited',
                'Too many failed login attempts. Try again later.',
                429,
            );
            return new Response(
                status: $response->status,
                headers: array_merge($response->headers, ['retry-after' => (string)self::RATE_WINDOW_SECONDS]),
                body: $response->body,
            );
        }

        $username = (string)($request->body['username'] ?? '');
        $password = (string)($request->body['password'] ?? '');

        $user = $this->users->findByUsername($username);
        $authenticated = $user !== null && $this->hasher->verify($password, $user->passwordHash);

        if ($user === null) {
            // Burn matching wall-clock so missing-user and wrong-password are indistinguishable.
            $this->hasher->verifyDummy($password);
        }

        $this->attempts->record($ip, $authenticated);

        if (!$authenticated || $user === null) {
            return JsonEnvelope::error(
                'invalid_credentials',
                'Invalid username or password.',
                401,
            );
        }

        $token = $this->jwt->issue($user->id, $user->role);

        return JsonEnvelope::success([
            'token' => $token['token'],
            'expires_at' => $token['expiresAt'],
            'token_type' => 'Bearer',
        ]);
    }

    private function clientIp(Request $request): string
    {
        $remote = $request->server['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($remote) && $remote !== '' ? $remote : '0.0.0.0';
    }
}
