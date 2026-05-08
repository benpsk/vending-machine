<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function testConstructDefaultsEmptyCollections(): void
    {
        $request = new Request(method: 'GET', path: '/');

        $this->assertSame('GET', $request->method);
        $this->assertSame('/', $request->path);
        $this->assertSame([], $request->query);
        $this->assertSame([], $request->body);
        $this->assertSame([], $request->headers);
        $this->assertSame([], $request->cookies);
        $this->assertSame([], $request->server);
    }

    /**
     * @param array<string, mixed> $server
     */
    #[DataProvider('pathParsingCases')]
    public function testFromArraysParsesMethodAndPath(array $server, string $expectedMethod, string $expectedPath): void
    {
        $request = Request::fromArrays(server: $server);

        $this->assertSame($expectedMethod, $request->method);
        $this->assertSame($expectedPath, $request->path);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function pathParsingCases(): iterable
    {
        yield 'plain root' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], 'GET', '/'];
        yield 'lowercased method is uppercased' =>
            [['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/x'], 'POST', '/x'];
        yield 'uri with query string strips query' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products?page=2'], 'GET', '/products'];
        yield 'uri with fragment strips fragment' =>
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x#frag'], 'GET', '/x'];
        yield 'missing method defaults to GET' =>
            [['REQUEST_URI' => '/y'], 'GET', '/y'];
        yield 'missing uri defaults to slash' =>
            [['REQUEST_METHOD' => 'GET'], 'GET', '/'];
        yield 'empty server defaults entirely' =>
            [[], 'GET', '/'];
    }

    public function testFromArraysCarriesQueryAndCookies(): void
    {
        $request = Request::fromArrays(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products'],
            query: ['page' => '2', 'sort' => 'price'],
            cookies: ['VENDING_SID' => 'abc'],
        );

        $this->assertSame(['page' => '2', 'sort' => 'price'], $request->query);
        $this->assertSame(['VENDING_SID' => 'abc'], $request->cookies);
    }

    public function testFromArraysCollectsHeadersFromServer(): void
    {
        $request = Request::fromArrays(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_AUTHORIZATION' => 'Bearer token-1',
            'HTTP_X_REQUEST_ID' => 'req-1',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '42',
            'NON_HTTP_KEY' => 'ignored',
        ]);

        $this->assertSame('Bearer token-1', $request->header('authorization'));
        $this->assertSame('req-1', $request->header('x-request-id'));
        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('42', $request->header('content-length'));
        $this->assertNull($request->header('non-http-key'));
    }

    public function testFromArraysDecodesJsonBodyOnNonGet(): void
    {
        $request = Request::fromArrays(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/products',
                'CONTENT_TYPE' => 'application/json',
            ],
            rawBody: '{"name":"Coke","price":3.99}',
        );

        $this->assertSame(['name' => 'Coke', 'price' => 3.99], $request->body);
    }

    public function testFromArraysIgnoresJsonBodyOnGet(): void
    {
        $request = Request::fromArrays(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'CONTENT_TYPE' => 'application/json',
            ],
            rawBody: '{"x":1}',
        );

        $this->assertSame([], $request->body);
    }

    public function testFromArraysFallsBackToPostForFormBody(): void
    {
        $request = Request::fromArrays(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/login',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            post: ['username' => 'admin', 'password' => 'x'],
            rawBody: 'username=admin&password=x',
        );

        $this->assertSame(['username' => 'admin', 'password' => 'x'], $request->body);
    }

    public function testFromArraysIgnoresInvalidJson(): void
    {
        $request = Request::fromArrays(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/products',
                'CONTENT_TYPE' => 'application/json',
            ],
            post: ['fallback' => 'kept'],
            rawBody: 'not-json{',
        );

        $this->assertSame(['fallback' => 'kept'], $request->body);
    }

    #[BackupGlobals(true)]
    public function testFromGlobalsReadsSuperglobals(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/products?page=2',
            'HTTP_X_TEST' => 'fromGlobals',
        ];
        $_GET = ['page' => '2'];
        $_POST = [];
        $_COOKIE = ['VENDING_SID' => 'sid-1'];

        $request = Request::fromGlobals();

        $this->assertSame('GET', $request->method);
        $this->assertSame('/products', $request->path);
        $this->assertSame(['page' => '2'], $request->query);
        $this->assertSame('fromGlobals', $request->header('x-test'));
        $this->assertSame(['VENDING_SID' => 'sid-1'], $request->cookies);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('bearerTokenCases')]
    public function testBearerToken(array $headers, ?string $expected): void
    {
        $request = new Request(method: 'GET', path: '/', headers: $headers);

        $this->assertSame($expected, $request->bearerToken());
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: ?string}>
     */
    public static function bearerTokenCases(): iterable
    {
        yield 'standard Bearer token' => [['authorization' => 'Bearer abc.def.ghi'], 'abc.def.ghi'];
        yield 'lowercase scheme' => [['authorization' => 'bearer abc'], 'abc'];
        yield 'multiple spaces accepted' => [['authorization' => 'Bearer   spaced'], 'spaced'];
        yield 'no header returns null' => [[], null];
        yield 'non-bearer scheme returns null' => [['authorization' => 'Basic dXNlcjpwYXNz'], null];
    }
}
