<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Storage\SessionStorageInterface;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(SessionStorageInterface $session): string
    {
        $existing = $session->get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $session->set(self::SESSION_KEY, $token);
        return $token;
    }

    public static function verify(SessionStorageInterface $session, ?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }
        $expected = $session->get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $candidate);
    }
}
