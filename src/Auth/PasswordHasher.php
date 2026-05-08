<?php

declare(strict_types=1);

namespace App\Auth;

final class PasswordHasher
{
    /**
     * Pre-computed bcrypt hash of the literal string `dummy`.
     * Used by {@see verifyDummy()} so the missing-user branch of login still
     * runs `password_verify` and consumes comparable wall-clock time as the
     * wrong-password branch — defeating user-enumeration via timing.
     */
    private const DUMMY_HASH = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8QzqK2c/ePBqRGLjwUVTqGGnG9Y/oS';

    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function verifyDummy(string $plain): bool
    {
        password_verify($plain, self::DUMMY_HASH);
        return false;
    }
}
