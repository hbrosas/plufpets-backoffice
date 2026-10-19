<?php

namespace App\Support;

class PosSaleRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_variant_id' => ['required', 'integer', 'exists:catalog_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.modifier_id' => ['required', 'integer', 'exists:modifiers,id'],
            'items.*.modifiers.*.option' => ['required', 'string', 'max:255'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'payments.*.amount_tendered' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'delivery_method_id' => ['nullable', 'integer', 'exists:delivery_methods,id'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'number' => ['nullable', 'string', 'regex:/^POS-\d{8}-\d{4}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'payments.required' => 'Add at least one payment.',
            'payments.min' => 'Add at least one payment.',
        ];
    }
}
