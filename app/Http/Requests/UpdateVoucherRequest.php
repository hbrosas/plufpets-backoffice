<?php

namespace App\Http\Requests;

use App\Models\Voucher;
use App\Support\VoucherRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $voucher = $this->route('voucher');

        return VoucherRules::for($voucher instanceof Voucher ? $voucher : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return VoucherRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
