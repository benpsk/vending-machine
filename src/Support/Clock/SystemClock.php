<?php

declare(strict_types=1);

namespace App\Support\Clock;

use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function nowTimestamp(): int
    {
        return time();
    }
}
