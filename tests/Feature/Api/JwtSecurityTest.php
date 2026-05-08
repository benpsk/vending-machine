<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\JwtAuthenticator;
use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class JwtSecurityTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testAlgNoneTokenIsRejected(): void
    {
        $userId = $this->seedUser('alice', Role::User);

        $header = self::base64Url(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'sub' => $userId,
            'role' => 'user',
            'iat' => time(),
            'exp' => time() + 900,
            'jti' => 'fake',
        ], JSON_THROW_ON_ERROR));
        $token = $header . '.' . $payload . '.';

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(401, $response->status);
        $this->assertSame('Bearer error="invalid_token"', $response->headers['www-authenticate'] ?? null);
    }

    public function testAlgRs256TokenIsRejected(): void
    {
        $userId = $this->seedUser('bob', Role::User);

        $header = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'sub' => $userId,
            'role' => 'user',
            'iat' => time(),
            'exp' => time() + 900,
            'jti' => 'fake',
        ], JSON_THROW_ON_ERROR));
        $token = $header . '.' . $payload . '.fakesig';

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(401, $response->status);
    }

    public function testTamperedSignatureRejected(): void
    {
        $userId = $this->seedUser('carol', Role::User);
        $jwt = $this->kernel->container->get(JwtAuthenticator::class);
        $token = $jwt->issue($userId, Role::User)['token'];

        // Flip every char to its successor in the base64url alphabet so the signature
        // is guaranteed different from the original regardless of which bytes it contains.
        $parts = explode('.', $token);
        $parts[2] = strtr(
            $parts[2],
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_',
            'BCDEFGHIJKLMNOPQRSTUVWXYZAbcdefghijklmnopqrstuvwxyza1234567890_-',
        );
        $tampered = implode('.', $parts);
        $this->assertNotSame($token, $tampered, 'tampering must actually change the signature');

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => "Bearer {$tampered}"],
        ));

        $this->assertSame(401, $response->status);
    }

    public function testTokenForDeletedUserRejected(): void
    {
        $userId = $this->seedUser('dave', Role::User);
        $jwt = $this->kernel->container->get(JwtAuthenticator::class);
        $token = $jwt->issue($userId, Role::User)['token'];

        $this->pdo->prepare('delete from users where id = :id')->execute(['id' => $userId]);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(401, $response->status);
    }

    private function seedUser(string $username, Role $role): int
    {
        $hasher = new PasswordHasher();
        return (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash('pw'),
            $role,
        );
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
