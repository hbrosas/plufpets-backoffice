<?php

namespace App\Support;

use App\Enums\TaxFeeType;
use App\Models\TaxFee;
use Illuminate\Validation\Rule;

class TaxFeeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?TaxFee $taxFee = null): array
    {
        $isPercent = request()->input('type') === TaxFeeType::Percent->value;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tax_fees', 'name')->ignore($taxFee?->id),
            ],
            'type' => ['required', Rule::enum(TaxFeeType::class)],
            'rate' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
                $isPercent ? 'max:100' : 'max:999999.99',
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a tax or fee name.',
            'name.unique' => 'That tax or fee already exists.',
            'type.required' => 'Choose percent or a fixed amount.',
            'rate.required' => 'Enter a rate.',
            'rate.max' => 'Percent rates cannot be more than 100.',
        ];
    }
}
