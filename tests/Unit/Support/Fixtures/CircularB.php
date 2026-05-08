<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class CircularB
{
    public function __construct(public readonly CircularA $a)
    {
    }
}
