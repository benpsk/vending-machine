<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\Required;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class RequiredTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(mixed $value, ?string $expectedFragment): void
    {
        $message = (new Required())->validate($value, 'name');

        if ($expectedFragment === null) {
            $this->assertNull($message);
        } else {
            $this->assertNotNull($message);
            $this->assertStringContainsString($expectedFragment, $message);
        }
    }

    /**
     * @return iterable<string, array{0: mixed, 1: ?string}>
     */
    public static function cases(): iterable
    {
        yield 'null fails'             => [null, 'required'];
        yield 'empty string fails'     => ['', 'required'];
        yield 'whitespace fails'       => ['   ', 'required'];
        yield 'empty array fails'      => [[], 'required'];
        yield 'non-empty string passes' => ['Coke', null];
        yield 'zero string passes'     => ['0', null];
        yield 'integer zero passes'    => [0, null];
        yield 'false passes'           => [false, null];
        yield 'array with item passes' => [['x'], null];
    }
}
