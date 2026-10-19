<?php

namespace App\Http\Requests;

use App\Enums\CatalogItemType;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockConversionRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'from_catalog_item_variant_id' => ['required', 'integer', 'exists:catalog_item_variants,id', 'different:to_catalog_item_variant_id'],
            'to_catalog_item_variant_id' => ['required', 'integer', 'exists:catalog_item_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'pieces_per_unit' => ['required', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Choose a location.',
            'from_catalog_item_variant_id.required' => 'Choose the product to convert.',
            'to_catalog_item_variant_id.required' => 'Choose the product to convert into.',
            'from_catalog_item_variant_id.different' => 'Choose a different product to convert into.',
            'quantity.required' => 'Enter a quantity.',
            'pieces_per_unit.required' => 'Enter how many pieces each unit becomes.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('to_catalog_item_variant_id')) {
                    return;
                }

                $destination = CatalogItemVariant::query()
                    ->with('catalogItem:id,type')
                    ->find($this->integer('to_catalog_item_variant_id'));

                if ($destination !== null && $destination->catalogItem?->type !== CatalogItemType::Product) {
                    $validator->errors()->add('to_catalog_item_variant_id', 'Choose a product to convert into.');
                }

                if ($validator->errors()->hasAny(['branch_id', 'from_catalog_item_variant_id', 'quantity'])) {
                    return;
                }

                $available = BranchInventory::query()
                    ->where('branch_id', $this->integer('branch_id'))
                    ->where('catalog_item_variant_id', $this->integer('from_catalog_item_variant_id'))
                    ->value('quantity') ?? 0;

                if ($this->integer('quantity') > $available) {
                    $validator->errors()->add('quantity', 'Only '.$available.' units are available at that location.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->toString() : null,
        ]);
    }
}
