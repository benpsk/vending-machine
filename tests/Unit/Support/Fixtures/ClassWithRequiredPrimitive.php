<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class ClassWithRequiredPrimitive
{
    public function __construct(public readonly int $count)
    {
    }
}
