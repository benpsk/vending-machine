<?php

declare(strict_types=1);

namespace App\Products;

use App\Validation\RuleInterface;
use App\Validation\Rules\IntegerRule;
use App\Validation\Rules\MaxLength;
use App\Validation\Rules\Min;
use App\Validation\Rules\Numeric;
use App\Validation\Rules\Required;

final class ProductValidationRules
{
    /**
     * @return array<string, list<RuleInterface>>
     */
    public static function rules(): array
    {
        return [
            'name' => [new Required(), new MaxLength(100)],
            'price' => [new Required(), new Numeric(), new Min(0.001)],
            'quantity_available' => [new Required(), new IntegerRule(), new Min(0)],
        ];
    }
}
