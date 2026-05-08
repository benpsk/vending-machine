<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class CircularA
{
    public function __construct(public readonly CircularB $b)
    {
    }
}
