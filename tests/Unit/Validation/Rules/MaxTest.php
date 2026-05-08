<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\Max;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class MaxTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(float|int $max, mixed $value, bool $shouldFail): void
    {
        $message = (new Max($max))->validate($value, 'price');

        if ($shouldFail) {
            $this->assertNotNull($message);
            $this->assertStringContainsString('less than or equal', $message);
        } else {
            $this->assertNull($message);
        }
    }

    /**
     * @return iterable<string, array{0: float|int, 1: mixed, 2: bool}>
     */
    public static function cases(): iterable
    {
        yield 'value < max passes'   => [100, 50, false];
        yield 'value == max passes'  => [100, 100, false];
        yield 'value > max fails'    => [100, 101, true];
        yield 'null skipped'         => [10, null, false];
        yield 'non-numeric skipped'  => [10, 'abc', false];
    }
}
