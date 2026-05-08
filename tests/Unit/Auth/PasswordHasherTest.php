<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\PasswordHasher;
use Tests\Support\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashIsBcryptAndVerifies(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3cret!');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue($hasher->verify('s3cret!', $hash));
    }

    public function testVerifyRejectsWrongPassword(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct');

        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function testVerifyDummyAlwaysReturnsFalse(): void
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verifyDummy(''));
        $this->assertFalse($hasher->verifyDummy('anything'));
    }

    public function testVerifyAndVerifyDummyTakeComparableTime(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct');

        $startReal = hrtime(true);
        $hasher->verify('wrong', $hash);
        $realNs = hrtime(true) - $startReal;

        $startDummy = hrtime(true);
        $hasher->verifyDummy('wrong');
        $dummyNs = hrtime(true) - $startDummy;

        // Loose bound: both should be in the same order of magnitude (within 5x).
        // bcrypt at PHP default cost is ~50ms+ on a modern CPU so this is robust.
        $this->assertLessThan($realNs * 5, $dummyNs);
        $this->assertLessThan($dummyNs * 5, $realNs);
    }
}
