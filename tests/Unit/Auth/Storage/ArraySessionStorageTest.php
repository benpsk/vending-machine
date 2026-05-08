<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Storage;

use App\Auth\Storage\ArraySessionStorage;
use Tests\Support\TestCase;

final class ArraySessionStorageTest extends TestCase
{
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $session = new ArraySessionStorage();
        $this->assertNull($session->get('missing'));
        $this->assertSame('default', $session->get('missing', 'default'));
    }

    public function testSetAndGetRoundTrip(): void
    {
        $session = new ArraySessionStorage();
        $session->set('user_id', 42);
        $this->assertSame(42, $session->get('user_id'));
        $this->assertTrue($session->has('user_id'));
    }

    public function testForgetRemovesKey(): void
    {
        $session = new ArraySessionStorage();
        $session->set('x', 1);
        $session->forget('x');
        $this->assertFalse($session->has('x'));
    }

    public function testClearEmptiesEverything(): void
    {
        $session = new ArraySessionStorage();
        $session->set('a', 1);
        $session->set('b', 2);

        $session->clear();

        $this->assertFalse($session->has('a'));
        $this->assertFalse($session->has('b'));
    }

    public function testStartFlipsStartedFlag(): void
    {
        $session = new ArraySessionStorage();
        $this->assertFalse($session->isStarted());

        $session->start();

        $this->assertTrue($session->isStarted());
    }

    public function testRegenerateIdChangesId(): void
    {
        $session = new ArraySessionStorage();
        $original = $session->id;

        $session->regenerateId();

        $this->assertNotSame($original, $session->id);
        $this->assertNotSame('', $session->id);
    }
}
