<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class ClassWithDefaultPrimitive
{
    public function __construct(
        public readonly ClassWithNoDeps $dep,
        public readonly int $count = 7,
    ) {
    }
}
