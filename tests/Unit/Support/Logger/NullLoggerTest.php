<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Logger;

use App\Support\Logger\NullLogger;
use PHPUnit\Framework\TestCase;

final class NullLoggerTest extends TestCase
{
    public function testCallsAreSilentlyDiscarded(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new NullLogger();
        $logger->info('ignored', ['k' => 'v']);
        $logger->warning('ignored');
        $logger->error('ignored', ['exception' => 'X']);
    }
}
