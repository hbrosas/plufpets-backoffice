<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use Illuminate\Validation\Rule;

class PurchaseOrderRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_variant_id' => [
                'required',
                'integer',
                'distinct',
                'exists:catalog_item_variants,id',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('catalog_item_variants', 'sku')],
            'barcode' => ['nullable', 'string', 'max:128', Rule::unique('catalog_item_variants', 'barcode')],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'type' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'supplier_id.required' => 'Choose a supplier.',
            'branch_id.required' => 'Choose the branch for this purchase order.',
            'items.required' => 'Add at least one product.',
            'items.min' => 'Add at least one product.',
            'items.*.catalog_item_variant_id.required' => 'Choose a product.',
            'items.*.catalog_item_variant_id.distinct' => 'Each product can only be listed once.',
            'items.*.quantity.required' => 'Enter a quantity.',
            'items.*.unit_cost.required' => 'Enter a unit cost.',
            'name.required' => 'Enter a product name.',
            'category_name.required' => 'Choose or add a category.',
            'selling_price.required' => 'Enter a selling price.',
            'unit_cost.required' => 'Enter a unit cost.',
            'sku.unique' => 'That SKU is already used.',
            'barcode.unique' => 'That barcode is already used.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function receive(): array
    {
        return [
            'delivery_receipt_number' => ['nullable', 'string', 'max:64'],
            'sales_invoice_number' => ['nullable', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public static function productType(): string
    {
        return CatalogItemType::Product->value;
    }
}
