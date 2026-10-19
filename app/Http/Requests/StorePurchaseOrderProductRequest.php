<?php

namespace App\Http\Requests;

use App\Support\PurchaseOrderRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderProductRequest extends FormRequest
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
        return PurchaseOrderRules::product();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PurchaseOrderRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'category_name' => $this->string('category_name')->trim()->toString(),
            'brand_name' => $this->filled('brand_name') ? $this->string('brand_name')->trim()->toString() : null,
            'sku' => $this->filled('sku') ? $this->string('sku')->trim()->toString() : null,
            'barcode' => $this->filled('barcode') ? $this->string('barcode')->trim()->toString() : null,
        ]);
    }
}
