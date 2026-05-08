<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Exceptions\JwtFailure;
use App\Auth\Exceptions\JwtVerificationException;
use App\Users\Role;

final class JwtClaims
{
    public function __construct(
        public readonly int $sub,
        public readonly Role $role,
        public readonly int $iat,
        public readonly int $exp,
        public readonly string $jti,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        foreach (['sub', 'role', 'iat', 'exp', 'jti'] as $required) {
            if (!array_key_exists($required, $payload)) {
                throw new JwtVerificationException(
                    JwtFailure::MissingClaim,
                    "JWT payload missing required claim: {$required}",
                );
            }
        }

        $roleValue = $payload['role'];
        if (!is_string($roleValue) || Role::tryFrom($roleValue) === null) {
            throw new JwtVerificationException(
                JwtFailure::MissingClaim,
                "JWT 'role' claim is not a valid Role value",
            );
        }

        return new self(
            sub: (int)$payload['sub'],
            role: Role::from($roleValue),
            iat: (int)$payload['iat'],
            exp: (int)$payload['exp'],
            jti: (string)$payload['jti'],
        );
    }
}
