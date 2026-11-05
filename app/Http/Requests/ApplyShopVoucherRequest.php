<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyShopVoucherRequest extends FormRequest
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
        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists(Branch::class, 'id')->where('is_active', true),
            ],
            'voucher_code' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voucher_code.required' => 'Enter a voucher code.',
            'voucher_code.max' => 'That voucher is not valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $voucher = strtoupper(trim((string) $this->input('voucher_code')));

        $this->merge([
            'voucher_code' => $voucher === '' ? null : $voucher,
        ]);
    }
}
