<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\MaxLength;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class MaxLengthTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(int $max, mixed $value, bool $shouldFail): void
    {
        $message = (new MaxLength($max))->validate($value, 'name');

        if ($shouldFail) {
            $this->assertNotNull($message);
            $this->assertStringContainsString('not be longer than', $message);
        } else {
            $this->assertNull($message);
        }
    }

    /**
     * @return iterable<string, array{0: int, 1: mixed, 2: bool}>
     */
    public static function cases(): iterable
    {
        yield 'short string'                  => [10, 'Coke', false];
        yield 'exactly at max'                => [4, 'Coke', false];
        yield 'over max'                      => [3, 'Coke', true];
        yield 'multibyte counted by chars'    => [4, 'café', false];
        yield 'null skipped'                  => [10, null, false];
        yield 'empty skipped'                 => [10, '', false];
        yield 'non-string skipped'            => [10, 42, false];
    }
}
