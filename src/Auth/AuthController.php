<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\LoginAttemptRepository;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Routing\Route;
use App\Support\Csrf;
use DateTimeImmutable;

final class AuthController
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const RATE_WINDOW_SECONDS = 900;
    private const NEXT_MAX_LENGTH = 512;

    public function __construct(
        private readonly View $view,
        private readonly SessionAuthenticator $authenticator,
        private readonly SessionStorageInterface $session,
        private readonly LoginAttemptRepository $attempts,
    ) {
    }

    #[Route('/login', methods: ['GET'], name: 'login.show')]
    public function showLogin(Request $request): Response
    {
        $next = $this->safeNext($request->query['next'] ?? null);
        return $this->renderLogin($request, next: $next);
    }

    #[Route('/login', methods: ['POST'], name: 'login')]
    public function login(Request $request): Response
    {
        $next = $this->safeNext($request->body['next'] ?? null);

        $ip = $this->clientIp($request);
        $since = (new DateTimeImmutable())->modify('-' . self::RATE_WINDOW_SECONDS . ' seconds');

        if ($this->attempts->countFailedSince($ip, $since) >= self::MAX_FAILED_ATTEMPTS) {
            return new Response(
                status: 429,
                headers: [
                    'content-type' => 'text/html; charset=utf-8',
                    'retry-after' => (string)self::RATE_WINDOW_SECONDS,
                ],
                body: '<!doctype html><h1>429 Too Many Requests</h1>'
                    . '<p>Too many failed login attempts. Try again later.</p>',
            );
        }

        $username = (string)($request->body['username'] ?? '');
        $password = (string)($request->body['password'] ?? '');

        $user = $this->authenticator->login($username, $password);
        $this->attempts->record($ip, $user !== null);

        if ($user === null) {
            return $this->renderLogin($request, error: 'Invalid username or password.', next: $next);
        }

        return Response::redirect($next);
    }

    #[Route('/logout', methods: ['POST'], name: 'logout')]
    public function logout(): Response
    {
        $this->authenticator->logout();
        return Response::redirect('/');
    }

    private function renderLogin(Request $request, ?string $error = null, string $next = '/'): Response
    {
        $body = $this->view->renderInLayout('auth/login', [
            'title' => 'Sign in',
            'currentUser' => $request->attribute('user'),
            'csrf' => Csrf::token($this->session),
            'error' => $error,
            'username' => (string)($request->body['username'] ?? ''),
            'next' => $next,
        ], 'layouts/public');
        return Response::html($body);
    }

    private function clientIp(Request $request): string
    {
        $remote = $request->server['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($remote) && $remote !== '' ? $remote : '0.0.0.0';
    }

    /**
     * Validate a `next` redirect target. Falls back to "/" on anything suspicious so
     * we can never be tricked into an open redirect or header injection.
     */
    private function safeNext(mixed $raw): string
    {
        if (!is_string($raw) || $raw === '') {
            return '/';
        }
        if (strlen($raw) > self::NEXT_MAX_LENGTH) {
            return '/';
        }
        // No CR/LF/whitespace anywhere — defends against header-injection paranoia.
        if (preg_match('/[\s\r\n]/', $raw) === 1) {
            return '/';
        }
        // Must be a relative path; reject protocol-relative `//evil.com/...`.
        if (!str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return '/';
        }
        // Avoid post-login loop back to /login itself.
        if ($raw === '/login' || str_starts_with($raw, '/login?') || str_starts_with($raw, '/login/')) {
            return '/';
        }
        return $raw;
    }
}
