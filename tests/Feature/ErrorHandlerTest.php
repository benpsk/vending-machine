<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Feature\Fixtures\BoomController;
use Tests\Support\TestKernel;

final class ErrorHandlerTest extends TestCase
{
    private function kernel(bool $debug): TestKernel
    {
        return new TestKernel(
            dirname(__DIR__, 2),
            extraControllers: [BoomController::class],
            debug: $debug,
        );
    }

    public function testWebRouteInProductionReturnsGeneric500Html(): void
    {
        $kernel = $this->kernel(debug: false);

        $response = $kernel->handle(new Request(method: 'GET', path: '/boom'));

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('text/html', (string)($response->headers['content-type'] ?? ''));
        $this->assertStringContainsString('500 Internal Server Error', $response->body);
        $this->assertStringNotContainsString('boom-web', $response->body);
        $this->assertStringNotContainsString('RuntimeException', $response->body);
    }

    public function testWebRouteInDebugIncludesExceptionClassAndTrace(): void
    {
        $kernel = $this->kernel(debug: true);

        $response = $kernel->handle(new Request(method: 'GET', path: '/boom'));

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('RuntimeException', $response->body);
        $this->assertStringContainsString('boom-web', $response->body);
        $this->assertStringContainsString('<pre>', $response->body);
    }

    public function testApiRouteInProductionReturnsGenericJsonEnvelope(): void
    {
        $kernel = $this->kernel(debug: false);

        $response = $kernel->handle(new Request(method: 'GET', path: '/api/boom'));

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('application/json', (string)($response->headers['content-type'] ?? ''));
        $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertNull($payload['data']);
        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertStringNotContainsString('boom-api', $response->body);
        $this->assertStringNotContainsString('RuntimeException', $response->body);
    }

    public function testApiRouteInDebugIncludesExceptionDetails(): void
    {
        $kernel = $this->kernel(debug: true);

        $response = $kernel->handle(new Request(method: 'GET', path: '/api/boom'));

        $this->assertSame(500, $response->status);
        $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertSame('boom-api', $payload['error']['message']);
        $this->assertSame('RuntimeException', $payload['error']['exception']);
        $this->assertArrayHasKey('trace', $payload['error']);
    }
}
