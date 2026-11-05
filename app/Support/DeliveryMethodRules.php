<?php

namespace App\Support;

use App\Models\DeliveryMethod;
use Illuminate\Validation\Rule;

class DeliveryMethodRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?DeliveryMethod $deliveryMethod = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('delivery_methods', 'name')->ignore($deliveryMethod?->id),
            ],
            'fee' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'instructions_for_all_branches' => ['required', 'boolean'],
            'branch_instructions' => ['nullable', 'array'],
            'branch_instructions.*.branch_id' => ['required', 'integer', 'distinct', 'exists:branches,id'],
            'branch_instructions.*.instructions' => ['nullable', 'string', 'max:1000'],
            'asks_for_address' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a delivery method name.',
            'name.unique' => 'That delivery method already exists.',
        ];
    }
}
