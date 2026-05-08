<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\Numeric;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class NumericTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(mixed $value, bool $shouldFail): void
    {
        $message = (new Numeric())->validate($value, 'price');

        if ($shouldFail) {
            $this->assertNotNull($message);
            $this->assertStringContainsString('numeric', $message);
        } else {
            $this->assertNull($message);
        }
    }

    /**
     * @return iterable<string, array{0: mixed, 1: bool}>
     */
    public static function cases(): iterable
    {
        yield 'integer'        => [42, false];
        yield 'float'          => [3.14, false];
        yield 'numeric string' => ['6.885', false];
        yield 'integer string' => ['7', false];
        yield 'negative'       => [-1, false];
        yield 'plain text'     => ['abc', true];
        yield 'mixed text'     => ['12abc', true];
        yield 'null skipped'   => [null, false];
        yield 'empty skipped'  => ['', false];
    }
}
