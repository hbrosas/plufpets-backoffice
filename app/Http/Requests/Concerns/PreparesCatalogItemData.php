<?php

namespace App\Http\Requests\Concerns;

use App\Support\CatalogItemRules;
use Illuminate\Validation\Validator;

trait PreparesCatalogItemData
{
    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))->values()->map(function (mixed $variant, int $index): array {
            $row = is_array($variant) ? $variant : [];
            $sku = trim((string) ($row['sku'] ?? ''));
            $barcode = preg_replace('/[\x00-\x1F\x7F]/', '', trim((string) ($row['barcode'] ?? ''))) ?? '';

            $prepared = [
                'name' => trim((string) ($row['name'] ?? '')),
                'sku' => $sku === '' ? null : $sku,
                'barcode' => $barcode === '' ? null : $barcode,
                'price' => $row['price'] ?? null,
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'remove_image' => filter_var($row['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'branch_prices' => collect($row['branch_prices'] ?? [])->map(function (mixed $price): array {
                    $priceRow = is_array($price) ? $price : [];
                    $amount = $priceRow['price'] ?? null;

                    return [
                        'branch_id' => $priceRow['branch_id'] ?? null,
                        'price' => $amount === '' || $amount === null ? null : $amount,
                    ];
                })->all(),
            ];

            if (filled($row['id'] ?? null)) {
                $prepared['id'] = $row['id'];
            }

            $image = $this->file("variants.{$index}.image");

            if ($image !== null) {
                $prepared['image'] = $image;
            }

            return $prepared;
        })->all();

        $modifierIds = collect($this->input('modifier_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        $modifierPrices = collect($this->input('modifier_prices', []))->map(function (mixed $price): array {
            $row = is_array($price) ? $price : [];

            return [
                'modifier_id' => (int) ($row['modifier_id'] ?? 0),
                'option' => trim((string) ($row['option'] ?? '')),
                'price' => $row['price'] ?? null,
                'branch_prices' => collect($row['branch_prices'] ?? [])->map(function (mixed $branchPrice): array {
                    $priceRow = is_array($branchPrice) ? $branchPrice : [];
                    $amount = $priceRow['price'] ?? null;

                    return [
                        'branch_id' => $priceRow['branch_id'] ?? null,
                        'price' => $amount === '' || $amount === null ? null : $amount,
                    ];
                })->all(),
            ];
        })->filter(fn (array $row): bool => $row['modifier_id'] > 0 && $row['option'] !== '')->values()->all();

        $category = $this->string('category_name')->trim()->toString();
        $brand = $this->string('brand_name')->trim()->toString();

        $this->merge([
            'type' => $this->string('type')->trim()->lower()->toString(),
            'name' => $this->string('name')->trim()->toString(),
            'category_name' => $category === '' ? null : $category,
            'brand_name' => $brand === '' ? null : $brand,
            'description' => $this->filled('description')
                ? $this->string('description')->trim()->toString()
                : null,
            'is_active' => $this->boolean('is_active'),
            'has_branch_pricing' => $this->boolean('has_branch_pricing'),
            'variants' => $variants,
            'modifier_ids' => $modifierIds,
            'modifier_prices' => $modifierPrices,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            CatalogItemRules::validateModifierOptionPrices($this->all(), $validator);
        });
    }
}
