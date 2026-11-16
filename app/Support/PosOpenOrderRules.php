<?php

namespace App\Support;

use Closure;

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
            'items.*.assigned_employee_ids' => ['nullable', 'array', self::uniqueEmployeeIds()],
            'items.*.assigned_employee_ids.*' => ['integer', 'exists:employees,id'],
            'items.*.assisted_employee_ids' => ['nullable', 'array', self::uniqueEmployeeIds()],
            'items.*.assisted_employee_ids.*' => ['integer', 'exists:employees,id'],
            'items.*.is_free' => ['sometimes', 'boolean'],
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

    private static function uniqueEmployeeIds(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $ids = array_map(intval(...), $value);

            if (count($ids) !== count(array_unique($ids))) {
                $fail('Choose each employee once.');
            }
        };
    }
}
