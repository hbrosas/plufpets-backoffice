<?php

namespace App\Support;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Validation\Rule;

class VoucherRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Voucher $voucher = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('vouchers', 'code')->ignore($voucher?->id),
            ],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:999999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.required' => 'Enter a voucher code.',
            'code.unique' => 'That voucher code already exists.',
            'code.regex' => 'Use letters, numbers, and dashes.',
            'type.required' => 'Choose a voucher type.',
            'amount.required' => 'Enter the voucher amount.',
            'amount.min' => 'Enter an amount of at least 0.01.',
        ];
    }
}
