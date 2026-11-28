<?php

namespace App\Http\Requests;

use App\Support\TestingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', TestingMode::existsBranch()],
            'scope' => ['required', Rule::in(['all', 'brand', 'category'])],
            'brand_id' => ['nullable', 'required_if:scope,brand', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'required_if:scope,category', 'integer', 'exists:categories,id'],
            'record_as_sale' => ['required', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:2000'],
            'lines.*.catalog_item_variant_id' => ['required', 'integer', 'distinct', 'exists:catalog_item_variants,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Choose a location.',
            'scope.required' => 'Choose all products, a brand, or a category.',
            'scope.in' => 'Choose all products, a brand, or a category.',
            'brand_id.required_if' => 'Choose a brand.',
            'category_id.required_if' => 'Choose a category.',
            'record_as_sale.required' => 'Choose whether to record this adjustment as a sale.',
            'lines.required' => 'Change at least one quantity.',
            'lines.min' => 'Change at least one quantity.',
            'lines.max' => 'Adjust at most 2,000 products at once.',
            'lines.*.catalog_item_variant_id.required' => 'Choose a product.',
            'lines.*.catalog_item_variant_id.distinct' => 'Each product can only be adjusted once.',
            'lines.*.quantity.required' => 'Enter a quantity.',
            'lines.*.quantity.integer' => 'Enter a whole number.',
            'lines.*.quantity.min' => 'Quantity cannot be negative.',
            'lines.*.quantity.max' => 'Quantity cannot be more than 1,000,000.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $scope = $this->string('scope')->trim()->toString();

        $this->merge([
            'scope' => $scope,
            'record_as_sale' => $this->boolean('record_as_sale'),
            'brand_id' => $scope === 'brand' && $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'category_id' => $scope === 'category' && $this->filled('category_id') ? $this->integer('category_id') : null,
        ]);
    }
}
