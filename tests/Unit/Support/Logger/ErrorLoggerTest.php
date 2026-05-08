<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Logger;

use App\Support\Logger\ErrorLogger;
use PHPUnit\Framework\TestCase;

final class ErrorLoggerTest extends TestCase
{
    private string $logFile;
    private string $previousErrorLog;

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'errorlogger_');
        if ($tmp === false) {
            $this->fail('failed to create temp log file');
        }
        $this->logFile = $tmp;
        $previous = ini_set('error_log', $this->logFile);
        $this->previousErrorLog = is_string($previous) ? $previous : '';
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testErrorLineContainsLevelMessageAndContext(): void
    {
        $logger = new ErrorLogger();
        $logger->error('boom', [
            'method' => 'GET',
            'path' => '/api/x',
            'user_id' => 42,
            'flag' => true,
            'absent' => null,
        ]);

        $contents = (string)file_get_contents($this->logFile);
        $this->assertStringContainsString('[ERROR]', $contents);
        $this->assertStringContainsString('boom', $contents);
        $this->assertStringContainsString('method=GET', $contents);
        $this->assertStringContainsString('path=/api/x', $contents);
        $this->assertStringContainsString('user_id=42', $contents);
        $this->assertStringContainsString('flag=true', $contents);
        $this->assertStringContainsString('absent=null', $contents);
    }

    public function testInfoAndWarningLevels(): void
    {
        $logger = new ErrorLogger();
        $logger->info('hello');
        $logger->warning('careful');

        $contents = (string)file_get_contents($this->logFile);
        $this->assertStringContainsString('[INFO] hello', $contents);
        $this->assertStringContainsString('[WARN] careful', $contents);
    }

    public function testQuotesValuesContainingSpaces(): void
    {
        $logger = new ErrorLogger();
        $logger->error('msg', ['note' => 'two words']);

        $contents = (string)file_get_contents($this->logFile);
        $this->assertStringContainsString('note="two words"', $contents);
    }
}
