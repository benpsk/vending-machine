<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\Rules\IntegerRule;
use App\Validation\Rules\MaxLength;
use App\Validation\Rules\Min;
use App\Validation\Rules\Numeric;
use App\Validation\Rules\Required;
use App\Validation\ValidationException;
use App\Validation\Validator;
use Tests\Support\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidInputPassesSilently(): void
    {
        $validator = new Validator();

        $validator->validate(
            ['name' => 'Coke', 'price' => '3.99', 'qty' => '20'],
            [
                'name' => [new Required(), new MaxLength(100)],
                'price' => [new Required(), new Numeric(), new Min(0.001)],
                'qty' => [new Required(), new IntegerRule(), new Min(0)],
            ],
        );

        // No exception thrown.
        $this->expectNotToPerformAssertions();
    }

    public function testCollectsAllErrorsForOneField(): void
    {
        $validator = new Validator();

        try {
            $validator->validate(
                ['price' => 'abc'],
                [
                    'price' => [new Required(), new Numeric(), new Min(0.001)],
                ],
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // 'abc' passes Required (non-empty), fails Numeric, Min skips non-numeric.
            // So one error for price.
            $this->assertArrayHasKey('price', $e->errors);
            $this->assertCount(1, $e->errors['price']);
            $this->assertStringContainsString('numeric', $e->errors['price'][0]);
        }
    }

    public function testCollectsErrorsAcrossMultipleFields(): void
    {
        $validator = new Validator();

        try {
            $validator->validate(
                ['name' => '', 'price' => '0', 'qty' => '-1'],
                [
                    'name' => [new Required()],
                    'price' => [new Required(), new Numeric(), new Min(0.001)],
                    'qty' => [new Required(), new IntegerRule(), new Min(0)],
                ],
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['name', 'price', 'qty'], array_keys($e->errors));
            $this->assertStringContainsString('required', $e->errors['name'][0]);
            $this->assertStringContainsString('greater than or equal', $e->errors['price'][0]);
            $this->assertStringContainsString('greater than or equal', $e->errors['qty'][0]);
        }
    }

    public function testMissingFieldIsTreatedAsNull(): void
    {
        $validator = new Validator();

        try {
            $validator->validate([], ['name' => [new Required()]]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors);
        }
    }

    public function testExceptionCarriesOriginalInput(): void
    {
        $validator = new Validator();
        $input = ['name' => '', 'price' => '0'];

        try {
            $validator->validate($input, [
                'name' => [new Required()],
                'price' => [new Min(1)],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame($input, $e->input);
        }
    }

    public function testCollectsMultipleErrorsForSameFieldWhenAllFire(): void
    {
        $validator = new Validator();

        try {
            $validator->validate(
                ['name' => null],
                ['name' => [new Required(), new MaxLength(5)]],
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // Required fires; MaxLength skips null. So one error.
            $this->assertCount(1, $e->errors['name']);
        }
    }
}
