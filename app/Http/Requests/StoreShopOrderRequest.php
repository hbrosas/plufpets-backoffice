<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopOrderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
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
            'branch_id.required' => 'Choose a branch.',
            'branch_id.exists' => 'Choose a branch.',
            'name.required' => 'Enter your name.',
            'phone.required' => 'Enter your phone number.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.catalog_item_variant_id.distinct' => 'Choose each product once.',
            'items.*.quantity.min' => 'Enter a quantity of at least 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')),
        ]);
    }
}
