<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\IntegerRule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class IntegerRuleTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(mixed $value, bool $shouldFail): void
    {
        $message = (new IntegerRule())->validate($value, 'qty');

        if ($shouldFail) {
            $this->assertNotNull($message);
            $this->assertStringContainsString('integer', $message);
        } else {
            $this->assertNull($message);
        }
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function cases(): iterable
    {
        yield 'native int'      => [42, false];
        yield 'integer string'  => ['42', false];
        yield 'negative string' => ['-5', false];
        yield 'zero string'     => ['0', false];
        yield 'decimal string'  => ['42.5', true];
        yield 'float'           => [3.14, true];
        yield 'plain text'      => ['abc', true];
        yield 'null skipped'    => [null, false];
        yield 'empty skipped'   => ['', false];
    }
}
