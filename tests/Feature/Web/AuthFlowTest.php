<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class AuthFlowTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testGetLoginRendersFormAndIssuesCsrfToken(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/login'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<form', $response->body);
        $this->assertStringContainsString('name="_token"', $response->body);
        $this->assertNotNull($this->kernel->session->get('_csrf_token'));
    }

    public function testPostLoginWithValidCredentialsRedirectsAndPopulatesSession(): void
    {
        $this->seedUser('admin', 'password', Role::Admin);

        // Prime CSRF.
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        $response = $this->postLogin($token, 'admin', 'password');

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
        $this->assertNotNull($this->kernel->session->get('user_id'));
        $this->assertSame('admin', $this->kernel->session->get('role'));
    }

    public function testPostLoginWithWrongPasswordRendersGenericError(): void
    {
        $this->seedUser('alice', 'real-password', Role::User);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        $response = $this->postLogin($token, 'alice', 'wrong-password');

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Invalid username or password.', $response->body);
        $this->assertFalse($this->kernel->session->has('user_id'));
    }

    public function testPostLoginWithMissingUserRendersTheSameError(): void
    {
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        $response = $this->postLogin($token, 'ghost', 'whatever');

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Invalid username or password.', $response->body);
    }

    public function testPostLoginWithoutCsrfTokenReturns403(): void
    {
        $this->seedUser('bob', 'pw', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['username' => 'bob', 'password' => 'pw'],
        ));

        $this->assertSame(403, $response->status);
        $this->assertFalse($this->kernel->session->has('user_id'));
    }

    public function testLogoutClearsSession(): void
    {
        $this->seedUser('carol', 'pw', Role::Admin);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $this->postLogin($token, 'carol', 'pw');
        $this->assertNotNull($this->kernel->session->get('user_id'));

        // After login the CSRF token persists; logout must include it.
        $logoutToken = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/logout',
            body: ['_token' => $logoutToken],
        ));

        $this->assertSame(302, $response->status);
        $this->assertFalse($this->kernel->session->has('user_id'));
    }

    public function testHomePageReflectsAuthenticatedUser(): void
    {
        $this->seedUser('dave', 'pw', Role::User);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $this->postLogin($token, 'dave', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/'));

        $this->assertSame(200, $response->status);
        // Authenticated state is signalled by the Log out button in the nav.
        $this->assertStringContainsString('Log out', $response->body);
        $this->assertStringNotContainsString('Log in', $response->body);
    }

    private function postLogin(string $token, string $username, string $password): Response
    {
        return $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => $username, 'password' => $password],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
    }

    private function seedUser(string $username, string $password, Role $role): void
    {
        $hasher = new PasswordHasher();
        (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash($password),
            $role,
        );
    }
}
