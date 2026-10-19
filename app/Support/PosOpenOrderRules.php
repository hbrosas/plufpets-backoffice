<?php

namespace App\Support;

class PosOpenOrderRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_variant_id' => ['required', 'integer', 'exists:catalog_item_variants,id'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.variant_name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.is_product' => ['required', 'boolean'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.modifier_id' => ['required', 'integer', 'exists:modifiers,id'],
            'items.*.modifiers.*.name' => ['required', 'string', 'max:255'],
            'items.*.modifiers.*.option' => ['required', 'string', 'max:255'],
            'items.*.modifiers.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a name for this open order.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
        ];
    }
}
