<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteShopOrderRequest extends FormRequest
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
            'delivery_method_id' => [
                'required',
                'integer',
                Rule::exists(DeliveryMethod::class, 'id')->where('is_active', true),
            ],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists(PaymentMethod::class, 'id')->where('is_active', true),
            ],
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.catalog_item_variant_id' => ['required', 'integer', 'distinct', 'exists:catalog_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_method_id.required' => 'Choose a delivery method.',
            'payment_method_id.required' => 'Choose a payment method.',
            'items.required' => 'Add at least one item.',
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
