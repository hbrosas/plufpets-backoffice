<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Models\BranchModifierOptionPrice;
use App\Models\BranchVariantPrice;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Support\CatalogItemRules;
use App\Support\CsvImportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportCatalogItems
{
    public const MAX_ROWS = 1000;

    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = [
        'type',
        'name',
        'description',
        'category',
        'brand',
        'variant_name',
        'sku',
        'barcode',
        'price',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL_COLUMNS = [
        'variant_active',
    ];

    public function __construct(private SaveCatalogItem $saveCatalogItem) {}

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(UploadedFile $file): array
    {
        $parsed = CsvImportFile::read(
            $file,
            self::REQUIRED_COLUMNS,
            self::OPTIONAL_COLUMNS,
            self::MAX_ROWS,
            'catalog rows',
        );

        [$groups, $errors] = $this->groups($parsed['rows'], array_key_exists('variant_active', $parsed['columns']));

        $imported = 0;

        if ($groups !== []) {
            DB::transaction(function () use ($groups, &$errors, &$imported): void {
                $imported = $this->saveGroups($groups, $errors);
            });
        }

        usort($errors, fn (array $left, array $right): int => $left['row'] <=> $right['row']);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<array{number: int, cells: array<string, string>}>  $rows
     * @return array{0: array<string, array<string, mixed>>, 1: list<array{row: int, message: string}>}
     */
    private function groups(array $rows, bool $hasVariantActive): array
    {
        $groups = [];
        $errors = [];
        $seenSkus = [];
        $seenBarcodes = [];
        $currentKey = null;

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $number = $row['number'];

            if ($cells['name'] === '') {
                if ($currentKey === null) {
                    $errors[] = ['row' => $number, 'message' => 'Enter a name.'];

                    continue;
                }

                $variant = $this->variantFromCells($cells, $hasVariantActive);
                $variantError = $this->firstError($this->variantAttributes($variant), CatalogItemRules::importVariant());

                if ($variantError !== null) {
                    $errors[] = ['row' => $number, 'message' => $variantError];

                    continue;
                }

                if ($this->isDuplicate($variant, $seenSkus, $seenBarcodes, $errors, $number)) {
                    continue;
                }

                $groups[$currentKey]['variants'][] = ['row' => $number, ...$variant];

                continue;
            }

            $productError = $this->firstError([
                'type' => Str::lower($cells['type']),
                'name' => $cells['name'],
                'description' => $cells['description'] === '' ? null : $cells['description'],
                'category_name' => $cells['category'] === '' ? null : $cells['category'],
                'brand_name' => $cells['brand'] === '' ? null : $cells['brand'],
                'is_active' => $this->toOptionalBoolean($cells['is_active']) ?? true,
            ], CatalogItemRules::importProduct());

            if ($productError !== null) {
                $errors[] = ['row' => $number, 'message' => $productError];

                continue;
            }

            $key = Str::lower($cells['type']).'|'.Str::lower($cells['name']);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'row' => $number,
                    'type' => Str::lower($cells['type']),
                    'name' => $cells['name'],
                    'description' => $cells['description'] === '' ? null : $cells['description'],
                    'category_name' => $cells['category'] === '' ? null : $cells['category'],
                    'brand_name' => $cells['brand'] === '' ? null : $cells['brand'],
                    'is_active' => $this->toOptionalBoolean($cells['is_active']),
                    'variants' => [],
                ];
            }

            $currentKey = $key;
            $variant = $this->variantFromCells($cells, $hasVariantActive);
            $variantError = $this->firstError($this->variantAttributes($variant), CatalogItemRules::importVariant());

            if ($variantError !== null) {
                $errors[] = ['row' => $number, 'message' => $variantError];

                continue;
            }

            if ($this->isDuplicate($variant, $seenSkus, $seenBarcodes, $errors, $number)) {
                continue;
            }

            $groups[$key]['variants'][] = ['row' => $number, ...$variant];
        }

        return [$groups, $errors];
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  list<array{row: int, message: string}>  $errors
     */
    private function saveGroups(array $groups, array &$errors): int
    {
        $items = $this->existingItems($groups);
        $storedCategories = $this->storedNames(Category::class, $this->filledNames($groups, 'category_name'));
        $storedBrands = $this->storedNames(Brand::class, $this->filledNames($groups, 'brand_name'));
        [$bySku, $byBarcode] = $this->existingVariants($groups);
        $imported = 0;

        foreach ($groups as $key => $group) {
            $item = $items[$key] ?? null;
            $accepted = [];

            foreach ($group['variants'] as $variant) {
                $owner = $variant['sku'] !== null ? $bySku->get($variant['sku']) : null;
                $belongsToItem = $owner instanceof CatalogItemVariant
                    && $item instanceof CatalogItem
                    && $owner->catalog_item_id === $item->id;

                if ($owner instanceof CatalogItemVariant && ! $belongsToItem) {
                    $errors[] = ['row' => $variant['row'], 'message' => 'That SKU is already used.'];

                    continue;
                }

                $barcodeOwner = $variant['barcode'] !== null ? $byBarcode->get($variant['barcode']) : null;

                if ($barcodeOwner instanceof CatalogItemVariant && $barcodeOwner->id !== $owner?->id) {
                    $errors[] = ['row' => $variant['row'], 'message' => 'That barcode is already used.'];

                    continue;
                }

                $accepted[] = $variant;
            }

            if ($accepted === []) {
                continue;
            }

            $categoryName = $this->canonicalName(
                $group['category_name'] ?? $item?->category?->name,
                $storedCategories,
            );
            $brandName = $this->canonicalName(
                $group['brand_name'] ?? $item?->brand?->name,
                $storedBrands,
            );

            if (! $item instanceof CatalogItem && $group['type'] === CatalogItemType::Product->value && $categoryName === null) {
                $errors[] = ['row' => $group['row'], 'message' => 'Enter a category.'];

                continue;
            }

            $this->saveCatalogItem->handle([
                'type' => $group['type'],
                'name' => $group['name'],
                'description' => $group['description'] ?? $item?->description,
                'badge' => $item?->badge,
                'is_active' => $group['is_active'] ?? $item?->is_active ?? true,
                'has_branch_pricing' => $item?->has_branch_pricing ?? false,
                'allows_preorder' => $item?->allows_preorder ?? true,
                'category_name' => $categoryName,
                'brand_name' => $brandName,
                'modifier_ids' => $item?->modifiers->pluck('id')->all() ?? [],
                'modifier_prices' => $item?->modifierOptionPrices->map(fn (CatalogItemModifierOptionPrice $price): array => [
                    'modifier_id' => $price->modifier_id,
                    'option' => $price->option,
                    'price' => $price->price,
                    'branch_prices' => $price->branchPrices->map(fn (BranchModifierOptionPrice $branchPrice): array => [
                        'branch_id' => $branchPrice->branch_id,
                        'price' => $branchPrice->price,
                    ])->all(),
                ])->all() ?? [],
                'variants' => $this->variantsForSave($item, $accepted, $bySku),
            ], $item);

            $imported += count($accepted);
        }

        return $imported;
    }

    /**
     * @param  list<array<string, mixed>>  $accepted
     * @return list<array<string, mixed>>
     */
    private function variantsForSave(?CatalogItem $item, array $accepted, Collection $bySku): array
    {
        $variants = [];

        foreach ($item?->variants ?? [] as $existing) {
            $variants[$existing->id] = [
                'id' => $existing->id,
                'name' => $existing->name,
                'sku' => $existing->sku,
                'barcode' => $existing->barcode,
                'price' => $existing->price,
                'is_active' => $existing->is_active,
                'branch_prices' => $existing->branchPrices->map(fn (BranchVariantPrice $price): array => [
                    'branch_id' => $price->branch_id,
                    'price' => $price->price,
                ])->all(),
            ];
        }

        foreach ($accepted as $variant) {
            $owner = $variant['sku'] !== null ? $bySku->get($variant['sku']) : null;

            if ($owner instanceof CatalogItemVariant && $item instanceof CatalogItem && $owner->catalog_item_id === $item->id) {
                $current = $variants[$owner->id];
                $variants[$owner->id] = [
                    ...$current,
                    'name' => $variant['name'],
                    'price' => $variant['price'],
                    'barcode' => $variant['barcode'] ?? $current['barcode'],
                    'is_active' => $variant['is_active'] ?? $current['is_active'],
                ];

                continue;
            }

            $variants['new-'.$variant['row']] = [
                'name' => $variant['name'],
                'sku' => $variant['sku'],
                'barcode' => $variant['barcode'],
                'price' => $variant['price'],
                'is_active' => $variant['is_active'] ?? true,
            ];
        }

        return array_values($variants);
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return array<string, CatalogItem>
     */
    private function existingItems(array $groups): array
    {
        $items = CatalogItem::query()
            ->with([
                'category',
                'brand',
                'variants.branchPrices',
                'modifiers',
                'modifierOptionPrices.branchPrices',
            ])
            ->where(function ($query) use ($groups): void {
                foreach ($groups as $group) {
                    $query->orWhere(function ($query) use ($group): void {
                        $query->where('type', $group['type'])
                            ->whereRaw('lower(name) = ?', [Str::lower($group['name'])]);
                    });
                }
            })
            ->orderBy('id')
            ->get();

        $byKey = [];

        foreach ($items as $item) {
            $key = $item->type->value.'|'.Str::lower($item->name);

            if (! isset($byKey[$key])) {
                $byKey[$key] = $item;
            }
        }

        return $byKey;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return array{0: Collection<string, CatalogItemVariant>, 1: Collection<string, CatalogItemVariant>}
     */
    private function existingVariants(array $groups): array
    {
        $skus = [];
        $barcodes = [];

        foreach ($groups as $group) {
            foreach ($group['variants'] as $variant) {
                if ($variant['sku'] !== null) {
                    $skus[] = $variant['sku'];
                }

                if ($variant['barcode'] !== null) {
                    $barcodes[] = $variant['barcode'];
                }
            }
        }

        $skus = array_values(array_unique($skus));
        $barcodes = array_values(array_unique($barcodes));

        if ($skus === [] && $barcodes === []) {
            return [collect(), collect()];
        }

        $matches = CatalogItemVariant::query()
            ->where(function ($query) use ($skus, $barcodes): void {
                if ($skus !== []) {
                    $query->whereIn('sku', $skus);
                }

                if ($barcodes !== []) {
                    $method = $skus === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('barcode', $barcodes);
                }
            })
            ->get();

        return [
            $matches->filter(fn (CatalogItemVariant $variant): bool => $variant->sku !== null)->keyBy('sku'),
            $matches->filter(fn (CatalogItemVariant $variant): bool => $variant->barcode !== null)->keyBy('barcode'),
        ];
    }

    /**
     * @param  array<string, string>  $cells
     * @return array{name: string, sku: ?string, barcode: ?string, price: string, is_active: ?bool}
     */
    private function variantFromCells(array $cells, bool $hasVariantActive): array
    {
        $barcode = preg_replace('/[\x00-\x1F\x7F]/', '', $cells['barcode']) ?: null;
        $activeCell = $hasVariantActive ? ($cells['variant_active'] ?? '') : $cells['is_active'];

        return [
            'name' => $cells['variant_name'],
            'sku' => $cells['sku'] === '' ? null : $cells['sku'],
            'barcode' => $barcode,
            'price' => $cells['price'],
            'is_active' => $this->toOptionalBoolean($activeCell),
        ];
    }

    /**
     * @param  array{name: string, sku: ?string, barcode: ?string, price: string, is_active: ?bool}  $variant
     * @return array<string, mixed>
     */
    private function variantAttributes(array $variant): array
    {
        return [
            'variant_name' => $variant['name'],
            'sku' => $variant['sku'],
            'barcode' => $variant['barcode'],
            'price' => $variant['price'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $rules
     */
    private function firstError(array $attributes, array $rules): ?string
    {
        $validator = Validator::make($attributes, $rules, CatalogItemRules::messages());

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return null;
    }

    /**
     * @param  array{sku: ?string, barcode: ?string}  $variant
     * @param  array<string, true>  $seenSkus
     * @param  array<string, true>  $seenBarcodes
     * @param  list<array{row: int, message: string}>  $errors
     */
    private function isDuplicate(array $variant, array &$seenSkus, array &$seenBarcodes, array &$errors, int $row): bool
    {
        if ($variant['sku'] !== null && isset($seenSkus[$variant['sku']])) {
            $errors[] = ['row' => $row, 'message' => 'This SKU is duplicated in the file.'];

            return true;
        }

        if ($variant['barcode'] !== null && isset($seenBarcodes[$variant['barcode']])) {
            $errors[] = ['row' => $row, 'message' => 'This barcode is duplicated in the file.'];

            return true;
        }

        if ($variant['sku'] !== null) {
            $seenSkus[$variant['sku']] = true;
        }

        if ($variant['barcode'] !== null) {
            $seenBarcodes[$variant['barcode']] = true;
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return list<string>
     */
    private function filledNames(array $groups, string $key): array
    {
        $names = [];

        foreach ($groups as $group) {
            if (is_string($group[$key]) && $group[$key] !== '') {
                $names[] = $group[$key];
            }
        }

        return $names;
    }

    /**
     * @param  class-string<Category|Brand>  $model
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function storedNames(string $model, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $records = $model::query()
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhereRaw('lower(name) = ?', [Str::lower($name)]);
                }
            })
            ->orderBy('id')
            ->get();

        $stored = [];

        foreach ($records as $record) {
            $key = Str::lower($record->name);

            if (! isset($stored[$key])) {
                $stored[$key] = $record->name;
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, string>  $stored
     */
    private function canonicalName(?string $name, array $stored): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return $stored[Str::lower($name)] ?? $name;
    }

    private function toOptionalBoolean(string $value): ?bool
    {
        if ($value === '') {
            return null;
        }

        return in_array(Str::lower($value), ['1', 'true', 'yes', 'active'], true);
    }
}
