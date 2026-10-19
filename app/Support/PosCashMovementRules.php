<?php

namespace App\Support;

use App\Enums\PosCashMovementType;
use Illuminate\Validation\Rule;

class PosCashMovementRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'type' => ['required', Rule::enum(PosCashMovementType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => 'Choose cash in or cash out.',
            'amount.required' => 'Enter an amount.',
            'reason.required' => 'Enter a reason.',
        ];
    }
}
