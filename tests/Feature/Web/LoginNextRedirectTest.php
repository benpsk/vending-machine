<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;
use App\Users\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class LoginNextRedirectTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testValidNextRoundTripRedirectsToTarget(): void
    {
        $this->seedUser('admin', 'password', Role::Admin);

        // GET /login?next=/admin/products → form embeds next as a hidden field.
        $get = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/login',
            query: ['next' => '/admin/products'],
        ));
        $this->assertSame(200, $get->status);
        $this->assertStringContainsString(
            '<input type="hidden" name="next" value="/admin/products">',
            $get->body,
        );

        $token = Csrf::token($this->kernel->session);
        $response = $this->postLogin($token, 'admin', 'password', next: '/admin/products');

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/products', $response->headers['location'] ?? null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function maliciousNextValues(): iterable
    {
        yield 'protocol-relative open redirect' => ['//evil.example.com/path'];
        yield 'absolute http URL' => ['http://evil.example.com/path'];
        yield 'absolute https URL' => ['https://evil.example.com/path'];
        yield 'loop back to /login' => ['/login'];
        yield 'loop back to /login with query' => ['/login?foo=bar'];
        yield 'newline header injection' => ["/admin/products\r\nX-Injected: yes"];
        yield 'tab whitespace' => ["/admin/\tstub"];
        yield 'space whitespace' => ['/admin/ stub'];
        yield 'over length limit (513 chars)' => ['/' . str_repeat('a', 512)];
        yield 'empty string' => [''];
        yield 'javascript: scheme' => ['javascript:alert(1)'];
    }

    #[DataProvider('maliciousNextValues')]
    public function testMaliciousNextFallsBackToHome(string $next): void
    {
        $this->seedUser('admin', 'password', Role::Admin);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        $response = $this->postLogin($token, 'admin', 'password', next: $next);

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/',
            $response->headers['location'] ?? null,
            "expected '/' but got '" . ($response->headers['location'] ?? 'null') . "' for next=" . json_encode($next),
        );
    }

    public function testGetLoginWithMaliciousNextRendersDefaultHiddenField(): void
    {
        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/login',
            query: ['next' => '//evil.example.com'],
        ));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(
            '<input type="hidden" name="next" value="/">',
            $response->body,
        );
        $this->assertStringNotContainsString('evil.example.com', $response->body);
    }

    private function postLogin(string $token, string $username, string $password, string $next): Response
    {
        return $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: [
                '_token' => $token,
                'username' => $username,
                'password' => $password,
                'next' => $next,
            ],
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
