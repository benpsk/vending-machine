<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Storage;

use App\Auth\Storage\PhpSessionStorage;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Support\TestCase;

/**
 * The session-mutating methods (start / regenerateId) require a real PHP session
 * which is awkward to drive in CLI. We test them in isolated subprocesses where
 * `headers_sent()` returns false. The pure dictionary methods (get/set/has/etc.)
 * touch only the {@see $_SESSION} superglobal and are tested in-process with
 * BackupGlobals=true so each test starts with a clean slate.
 */
#[BackupGlobals(true)]
final class PhpSessionStorageTest extends TestCase
{
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $_SESSION = [];
        $storage = new PhpSessionStorage('TEST_SID', false);

        $this->assertNull($storage->get('missing'));
        $this->assertSame('default', $storage->get('missing', 'default'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $_SESSION = [];
        $storage = new PhpSessionStorage('TEST_SID', false);

        $storage->set('user_id', 42);

        $this->assertSame(42, $storage->get('user_id'));
        $this->assertArrayHasKey('user_id', $_SESSION);
    }

    public function testHasReflectsKeyPresence(): void
    {
        $_SESSION = ['foo' => 'bar'];
        $storage = new PhpSessionStorage('TEST_SID', false);

        $this->assertTrue($storage->has('foo'));
        $this->assertFalse($storage->has('baz'));
    }

    public function testForgetRemovesKey(): void
    {
        $_SESSION = ['x' => 1];
        $storage = new PhpSessionStorage('TEST_SID', false);

        $storage->forget('x');

        $this->assertArrayNotHasKey('x', $_SESSION);
    }

    public function testClearEmptiesSessionData(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];
        $storage = new PhpSessionStorage('TEST_SID', false);

        $storage->clear();

        $this->assertSame([], $_SESSION);
    }

    #[RunInSeparateProcess]
    public function testStartConfiguresCookieAndStartsSession(): void
    {
        $storage = new PhpSessionStorage('CUSTOM_SID', false);

        $storage->start();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('CUSTOM_SID', session_name());
        $params = session_get_cookie_params();
        $this->assertTrue($params['httponly']);
        $this->assertFalse($params['secure']);
        $this->assertSame('Lax', $params['samesite']);
    }

    #[RunInSeparateProcess]
    public function testStartIsIdempotent(): void
    {
        $storage = new PhpSessionStorage('CUSTOM_SID', false);
        $storage->start();
        $idAfterFirst = session_id();

        $storage->start();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame($idAfterFirst, session_id());
    }

    #[RunInSeparateProcess]
    public function testRegenerateIdRotatesSessionId(): void
    {
        $storage = new PhpSessionStorage('CUSTOM_SID', false);
        $storage->start();
        $original = session_id();

        $storage->regenerateId();

        $this->assertNotSame($original, session_id());
        $this->assertNotSame('', session_id());
    }

    #[RunInSeparateProcess]
    public function testRegenerateIdIsNoopWhenSessionInactive(): void
    {
        $storage = new PhpSessionStorage('CUSTOM_SID', false);

        $storage->regenerateId();

        $this->assertSame(PHP_SESSION_NONE, session_status());
    }
}
