<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Request;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class HomeSmokeTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testRootReturns200WithBody(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/'));

        $this->assertSame(200, $response->status);
        $this->assertNotSame('', $response->body);
        $this->assertStringContainsString('Vending Machine', $response->body);
    }

    public function testHeadOnRootMatchesGetRoute(): void
    {
        $response = $this->kernel->handle(new Request(method: 'HEAD', path: '/'));

        $this->assertSame(200, $response->status);
    }

    public function testUnknownPathReturns404(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/does-not-exist'));

        $this->assertSame(404, $response->status);
    }

    public function testPostOnGetOnlyRouteReturns405WithAllowHeader(): void
    {
        $response = $this->kernel->handle(new Request(method: 'POST', path: '/'));

        $this->assertSame(405, $response->status);
        $this->assertSame('GET, HEAD', $response->headers['allow'] ?? null);
    }
}
