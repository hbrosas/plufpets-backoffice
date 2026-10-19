<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use App\Models\Modifier;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogItemRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?CatalogItem $catalogItem = null): array
    {
        $keptVariantIds = collect(request()->input('variants', []))
            ->pluck('id')
            ->filter()
            ->all();

        return [
            'type' => ['required', Rule::enum(CatalogItemType::class)],
            'name' => ['required', 'string', 'max:255'],
            'category_name' => [
                'nullable',
                'string',
                'max:255',
                'required_if:type,'.CatalogItemType::Product->value,
            ],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'has_branch_pricing' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => $catalogItem === null
                ? ['prohibited']
                : [
                    'nullable',
                    'integer',
                    Rule::exists('catalog_item_variants', 'id')->where(
                        fn ($query) => $query->where('catalog_item_id', $catalogItem->id),
                    ),
                ],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:64',
                'distinct',
                Rule::unique('catalog_item_variants', 'sku')->where(
                    fn ($query) => $query->whereNotIn('id', $keptVariantIds === [] ? [0] : $keptVariantIds),
                ),
            ],
            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:128',
                'distinct',
                Rule::unique('catalog_item_variants', 'barcode')->where(
                    fn ($query) => $query->whereNotIn('id', $keptVariantIds === [] ? [0] : $keptVariantIds),
                ),
            ],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'variants.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'variants.*.remove_image' => ['sometimes', 'boolean'],
            'variants.*.is_active' => ['required', 'boolean'],
            'variants.*.branch_prices' => ['nullable', 'array'],
            'variants.*.branch_prices.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'variants.*.branch_prices.*.price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'modifier_ids' => ['nullable', 'array'],
            'modifier_ids.*' => ['integer', 'distinct', 'exists:modifiers,id'],
            'modifier_prices' => ['nullable', 'array'],
            'modifier_prices.*.modifier_id' => ['required', 'integer', 'exists:modifiers,id'],
            'modifier_prices.*.option' => ['required', 'string', 'max:255'],
            'modifier_prices.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'modifier_prices.*.branch_prices' => ['nullable', 'array'],
            'modifier_prices.*.branch_prices.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'modifier_prices.*.branch_prices.*.price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => 'Choose whether this is a product or a service.',
            'name.required' => 'Enter a name.',
            'category_name.required_if' => 'Enter a category.',
            'variants.required' => 'Add at least one variant.',
            'variants.min' => 'Add at least one variant.',
            'variants.*.name.required' => 'Enter a variant name.',
            'variants.*.price.required' => 'Enter a variant price.',
            'variants.*.barcode.unique' => 'That barcode is already used.',
            'variants.*.barcode.distinct' => 'Each variant needs a different barcode.',
            'barcode.unique' => 'That barcode is already used.',
            'modifier_prices.*.price.required' => 'Enter a price for this option.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateModifierOptionPrices(array $data, Validator $validator): void
    {
        $modifierIds = collect($data['modifier_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->all();
        $modifiers = Modifier::query()
            ->whereIn('id', $modifierIds === [] ? [0] : $modifierIds)
            ->get(['id', 'options'])
            ->keyBy('id');

        foreach ($data['modifier_prices'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $modifierId = (int) ($row['modifier_id'] ?? 0);

            if ($modifierId === 0) {
                continue;
            }

            if (! in_array($modifierId, $modifierIds, true)) {
                $validator->errors()->add(
                    'modifier_prices.'.$index.'.modifier_id',
                    'Enable this modifier before setting option prices.',
                );

                continue;
            }

            $options = $modifiers->get($modifierId)?->options ?? [];

            if (! in_array((string) ($row['option'] ?? ''), $options, true)) {
                $validator->errors()->add(
                    'modifier_prices.'.$index.'.option',
                    'That option is not part of this modifier.',
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function importRow(): array
    {
        return [
            'type' => ['required', Rule::enum(CatalogItemType::class)],
            'name' => ['required', 'string', 'max:255'],
            'category_name' => [
                'nullable',
                'string',
                'max:255',
                'required_if:type,'.CatalogItemType::Product->value,
            ],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'variant_name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('catalog_item_variants', 'sku')],
            'barcode' => ['nullable', 'string', 'max:128', Rule::unique('catalog_item_variants', 'barcode')],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
        ];
    }
}
