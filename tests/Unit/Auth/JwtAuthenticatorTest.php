<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Exceptions\JwtFailure;
use App\Auth\Exceptions\JwtVerificationException;
use App\Auth\JwtAuthenticator;
use App\Users\Role;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Tests\Support\Clock\FixedClock;
use Tests\Support\TestCase;

final class JwtAuthenticatorTest extends TestCase
{
    private const SECRET = 'test-secret-bytes-32-characters!!';

    public function testIssueAndVerifyHappyPath(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        $token = $auth->issue(userId: 7, role: Role::Admin);
        $claims = $auth->verify($token['token']);

        $this->assertSame(7, $claims->sub);
        $this->assertSame(Role::Admin, $claims->role);
        $this->assertSame($clock->nowTimestamp(), $claims->iat);
        $this->assertSame($clock->nowTimestamp() + 900, $claims->exp);
        $this->assertSame($claims->exp, $token['expiresAt']);
        $this->assertNotSame('', $claims->jti);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 60, clock: $clock);

        $token = $auth->issue(7, Role::User);
        $clock->advance('+10 minutes');

        try {
            $auth->verify($token['token']);
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertSame(JwtFailure::Expired, $e->failure);
        }
    }

    public function testTamperedSignatureRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        $token = $auth->issue(7, Role::User);
        // Permute every char in the base64url alphabet so the signature is guaranteed
        // to mutate regardless of which bytes it happens to contain.
        $parts = explode('.', $token['token']);
        $parts[2] = strtr(
            $parts[2],
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_',
            'BCDEFGHIJKLMNOPQRSTUVWXYZAbcdefghijklmnopqrstuvwxyza1234567890_-',
        );
        $tampered = implode('.', $parts);
        $this->assertNotSame($token['token'], $tampered, 'tampering must change the signature');

        try {
            $auth->verify($tampered);
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertSame(JwtFailure::InvalidSignature, $e->failure);
        }
    }

    public function testAlgNoneTokenRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        // Hand-craft an alg=none token. Library should refuse via the explicit HS256 allowlist.
        $header = self::base64Url(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'sub' => 7,
            'role' => 'admin',
            'iat' => $clock->nowTimestamp(),
            'exp' => $clock->nowTimestamp() + 900,
            'jti' => 'fake',
        ], JSON_THROW_ON_ERROR));
        $token = $header . '.' . $payload . '.';

        try {
            $auth->verify($token);
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertSame(JwtFailure::WrongAlgorithm, $e->failure);
        }
    }

    public function testAlgRs256TokenRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        // Hand-craft a token claiming RS256. Library should refuse.
        $header = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'sub' => 7,
            'role' => 'admin',
            'iat' => $clock->nowTimestamp(),
            'exp' => $clock->nowTimestamp() + 900,
            'jti' => 'fake',
        ], JSON_THROW_ON_ERROR));
        // Use a fake signature; library will reject due to alg mismatch before checking sig.
        $token = $header . '.' . $payload . '.fakesig';

        try {
            $auth->verify($token);
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertSame(JwtFailure::WrongAlgorithm, $e->failure);
        }
    }

    public function testMalformedTokenRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));
        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        try {
            $auth->verify('not-a-jwt');
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertContains($e->failure, [JwtFailure::Malformed, JwtFailure::WrongAlgorithm]);
        }
    }

    public function testMissingClaimRejected(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-05-08 10:00:00'));

        // Encode a valid HS256 token that lacks the `role` claim.
        $payload = [
            'sub' => 7,
            // 'role' intentionally missing
            'iat' => $clock->nowTimestamp(),
            'exp' => $clock->nowTimestamp() + 900,
            'jti' => 'fake',
        ];
        $token = JWT::encode($payload, self::SECRET, 'HS256');

        $auth = new JwtAuthenticator(self::SECRET, ttlSeconds: 900, clock: $clock);

        try {
            $auth->verify($token);
            $this->fail('Expected JwtVerificationException');
        } catch (JwtVerificationException $e) {
            $this->assertSame(JwtFailure::MissingClaim, $e->failure);
        }
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
