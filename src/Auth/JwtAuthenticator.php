<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\JwtFailure;
use App\Auth\Exceptions\JwtVerificationException;
use App\Support\Clock\ClockInterface;
use App\Users\Role;
use DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class JwtAuthenticator
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{token: string, expiresAt: int}
     */
    public function issue(int $userId, Role $role): array
    {
        $issuedAt = $this->clock->nowTimestamp();
        $expiresAt = $issuedAt + $this->ttlSeconds;

        $payload = [
            'sub' => $userId,
            'role' => $role->value,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $token = JWT::encode($payload, $this->secret, self::ALGORITHM);

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    public function verify(string $token): JwtClaims
    {
        // Pin the algorithm — pass only HS256 to JWT::decode so a token with
        // alg=none or alg=RS256 is rejected by the library, not silently trusted.
        $key = new Key($this->secret, self::ALGORITHM);

        // Pin the "now" used for exp/nbf comparisons to our injected clock so tests
        // remain deterministic (FixedClock can fast-forward without touching time()).
        JWT::$timestamp = $this->clock->nowTimestamp();

        try {
            $decoded = JWT::decode($token, $key);
        } catch (ExpiredException $e) {
            throw new JwtVerificationException(JwtFailure::Expired, $e->getMessage());
        } catch (SignatureInvalidException $e) {
            throw new JwtVerificationException(JwtFailure::InvalidSignature, $e->getMessage());
        } catch (UnexpectedValueException $e) {
            // firebase/php-jwt throws this for unknown/missing alg, including alg=none.
            throw new JwtVerificationException(JwtFailure::WrongAlgorithm, $e->getMessage());
        } catch (DomainException $e) {
            throw new JwtVerificationException(JwtFailure::Malformed, $e->getMessage());
        }

        /** @var array<string, mixed> $payload */
        $payload = (array)$decoded;
        return JwtClaims::fromPayload($payload);
    }
}
