<?php

namespace App\Support;

use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;

class PaymentMethodRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?PaymentMethod $paymentMethod = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_methods', 'name')->ignore($paymentMethod?->id),
            ],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_cash' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a payment method name.',
            'name.unique' => 'That payment method already exists.',
            'is_cash.required' => 'Choose Cash or Charge.',
        ];
    }
}
