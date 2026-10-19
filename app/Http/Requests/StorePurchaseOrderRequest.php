<?php

namespace App\Http\Requests;

use App\Enums\CatalogItemType;
use App\Models\CatalogItemVariant;
use App\Support\PurchaseOrderRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
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
        return PurchaseOrderRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PurchaseOrderRules::messages();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $variantIds = collect($this->input('items', []))->pluck('catalog_item_variant_id')->filter()->all();

                $serviceIds = CatalogItemVariant::query()
                    ->whereIn('id', $variantIds === [] ? [0] : $variantIds)
                    ->whereHas('catalogItem', fn ($query) => $query->where('type', CatalogItemType::Service))
                    ->pluck('id')
                    ->all();

                foreach ($this->input('items', []) as $index => $item) {
                    if (in_array((int) ($item['catalog_item_variant_id'] ?? 0), $serviceIds, true)) {
                        $validator->errors()->add(
                            'items.'.$index.'.catalog_item_variant_id',
                            'Services cannot be purchased as stock.',
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->toString() : null,
            'branch_id' => $this->filled('branch_id') ? $this->integer('branch_id') : null,
        ]);
    }
}
