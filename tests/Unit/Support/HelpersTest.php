<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class HelpersTest extends TestCase
{
    #[DataProvider('escapeCases')]
    public function testEscapesHtml(?string $input, string $expected): void
    {
        $this->assertSame($expected, e($input));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string}>
     */
    public static function escapeCases(): iterable
    {
        yield 'plain text untouched' => ['hello', 'hello'];
        yield 'less-than escaped' => ['<script>', '&lt;script&gt;'];
        yield 'ampersand escaped' => ['Tom & Jerry', 'Tom &amp; Jerry'];
        yield 'double quote escaped' => ['"x"', '&quot;x&quot;'];
        yield 'single quote escaped' => ["it's", 'it&apos;s'];
        yield 'null treated as empty' => [null, ''];
        yield 'empty stays empty' => ['', ''];
    }

    #[DataProvider('urlCases')]
    public function testUrlNormalisesPath(string $input, string $expected): void
    {
        $this->assertSame($expected, url($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function urlCases(): iterable
    {
        yield 'leading slash' => ['/products', '/products'];
        yield 'no leading slash' => ['products', '/products'];
        yield 'root' => ['/', '/'];
    }

    #[DataProvider('assetCases')]
    public function testAssetPrependsAssetsPrefix(string $input, string $expected): void
    {
        $this->assertSame($expected, asset($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function assetCases(): iterable
    {
        yield 'css path' => ['css/app.css', '/assets/css/app.css'];
        yield 'leading slash stripped' => ['/js/validation.js', '/assets/js/validation.js'];
    }

    public function testUrlDefaultsToRoot(): void
    {
        $this->assertSame('/', url());
    }
}
