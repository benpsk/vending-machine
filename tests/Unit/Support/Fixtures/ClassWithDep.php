<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class ClassWithDep
{
    public function __construct(public readonly ClassWithNoDeps $dep)
    {
    }
}
