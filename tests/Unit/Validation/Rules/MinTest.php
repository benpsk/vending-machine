<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use App\Validation\Rules\Min;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class MinTest extends TestCase
{
    #[DataProvider('cases')]
    public function testValidate(float|int $min, mixed $value, bool $shouldFail): void
    {
        $message = (new Min($min))->validate($value, 'price');

        if ($shouldFail) {
            $this->assertNotNull($message);
            $this->assertStringContainsString('greater than or equal', $message);
        } else {
            $this->assertNull($message);
        }
    }

    /**
     * @return iterable<string, array{0: float|int, 1: mixed, 2: bool}>
     */
    public static function cases(): iterable
    {
        yield 'price > min passes'        => [0.001, '3.99', false];
        yield 'price == min passes'       => [0.001, '0.001', false];
        yield 'price < min fails'         => [0.001, '0', true];
        yield 'negative fails'            => [0, '-5', true];
        yield 'integer min, integer val'  => [10, 5, true];
        yield 'integer min, equal'        => [10, 10, false];
        yield 'null skipped'              => [0, null, false];
        yield 'empty skipped'             => [0, '', false];
        yield 'non-numeric skipped'       => [0, 'abc', false];
    }
}
