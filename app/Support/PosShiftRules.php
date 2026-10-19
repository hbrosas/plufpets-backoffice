<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class PosShiftRules
{
    /**
     * @return array<string, mixed>
     */
    public static function open(): array
    {
        return self::denominations();
    }

    /**
     * @return array<string, mixed>
     */
    public static function close(): array
    {
        return [
            ...self::denominations(),
            'remit_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'confirm_variance' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function denominations(): array
    {
        return [
            'denominations' => ['required', 'array', 'min:1'],
            'denominations.*.denomination' => ['required', 'numeric', Rule::in(CashDenominations::values())],
            'denominations.*.quantity' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'denominations.required' => 'Enter the cash breakdown.',
            'remit_amount.required' => 'Enter the amount to remit.',
        ];
    }
}
