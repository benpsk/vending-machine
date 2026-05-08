<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use JsonException;
use Tests\Support\TestCase;

final class ResponseTest extends TestCase
{
    public function testHtmlFactorySetsContentTypeAndStatus(): void
    {
        $response = Response::html('<h1>hi</h1>');

        $this->assertSame(200, $response->status);
        $this->assertSame('<h1>hi</h1>', $response->body);
        $this->assertSame('text/html; charset=utf-8', $response->headers['content-type']);
    }

    public function testHtmlFactoryAcceptsCustomStatus(): void
    {
        $response = Response::html('<h1>nope</h1>', 404);

        $this->assertSame(404, $response->status);
    }

    public function testJsonFactoryEncodesPayload(): void
    {
        $response = Response::json(['data' => ['id' => 1, 'name' => 'Coke'], 'error' => null]);

        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['content-type']);
        $this->assertSame('{"data":{"id":1,"name":"Coke"},"error":null}', $response->body);
    }

    public function testJsonFactoryThrowsOnUnencodableValue(): void
    {
        $this->expectException(JsonException::class);

        Response::json(["\xB1\x31"]);
    }

    public function testRedirectFactorySetsLocationHeader(): void
    {
        $response = Response::redirect('/login');

        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['location']);
        $this->assertSame('', $response->body);
    }

    public function testRedirectFactoryAcceptsCustomStatus(): void
    {
        $response = Response::redirect('/x', 301);

        $this->assertSame(301, $response->status);
    }

    public function testSendEchoesBody(): void
    {
        $response = Response::html('<p>body content</p>');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('<p>body content</p>', $output);
    }
}
