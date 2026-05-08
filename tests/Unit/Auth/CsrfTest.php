<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Storage\ArraySessionStorage;
use App\Support\Csrf;
use Tests\Support\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsIssuedAndCachedInSession(): void
    {
        $session = new ArraySessionStorage();

        $first = Csrf::token($session);
        $second = Csrf::token($session);

        $this->assertSame($first, $second);
        $this->assertNotSame('', $first);
    }

    public function testVerifyAcceptsCorrectToken(): void
    {
        $session = new ArraySessionStorage();
        $token = Csrf::token($session);

        $this->assertTrue(Csrf::verify($session, $token));
    }

    public function testVerifyRejectsWrongToken(): void
    {
        $session = new ArraySessionStorage();
        Csrf::token($session);

        $this->assertFalse(Csrf::verify($session, 'tampered'));
    }

    public function testVerifyRejectsMissingToken(): void
    {
        $session = new ArraySessionStorage();
        Csrf::token($session);

        $this->assertFalse(Csrf::verify($session, null));
        $this->assertFalse(Csrf::verify($session, ''));
    }

    public function testVerifyRejectsWhenSessionHasNoTokenYet(): void
    {
        $session = new ArraySessionStorage();

        $this->assertFalse(Csrf::verify($session, 'whatever'));
    }
}
