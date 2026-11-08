<?php

namespace App\Support;

use Illuminate\Validation\Rule;

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
            'items.*.assigned_employee_ids' => ['nullable', 'array'],
            'items.*.assigned_employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'items.*.assisted_employee_ids' => ['nullable', 'array'],
            'items.*.assisted_employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'items.*.is_free' => ['sometimes', 'boolean'],
            'payments' => ['present', 'array'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'payments.*.amount_tendered' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'delivery_method_id' => ['nullable', 'integer', 'exists:delivery_methods,id'],
            'delivery_schedule_id' => ['nullable', 'integer'],
            'preferred_on' => ['nullable', 'date_format:Y-m-d'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'tax_fee_ids' => ['nullable', 'array'],
            'tax_fee_ids.*' => ['integer', 'distinct', Rule::exists('tax_fees', 'id')->where('is_active', true)],
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
            'items.*.assigned_employee_ids.*.exists' => 'Choose an active employee with a record.',
            'items.*.assisted_employee_ids.*.exists' => 'Choose an active employee with a record.',
            'items.*.assigned_employee_ids.*.distinct' => 'Choose each employee once.',
            'items.*.assisted_employee_ids.*.distinct' => 'Choose each employee once.',
            'payments.required' => 'Add at least one payment.',
            'payments.min' => 'Add at least one payment.',
            'tax_fee_ids.*.exists' => 'Choose an active tax.',
            'tax_fee_ids.*.distinct' => 'Choose each tax once.',
            'delivery_schedule_id.integer' => 'Choose a preferred slot.',
            'preferred_on.date_format' => 'Choose a preferred slot.',
        ];
    }
}
