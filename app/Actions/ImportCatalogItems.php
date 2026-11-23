<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Support\CatalogItemRules;
use App\Support\CsvImportFile;
use App\Support\CsvImportResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    /**
     * @return array{columns: list<string>, rows: list<array{row: int, values: list<string>}>}
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parsed($file);

        return [
            'columns' => $parsed['columns'],
            'rows' => CsvImportResult::table($parsed['rows'], $parsed['columns']),
        ];
    }

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>, logs: array{columns: list<string>, rows: list<array{row: int, values: list<string>, imported: bool, message: string}>}}
     */
    public function handle(UploadedFile $file): array
    {
        set_time_limit(120);

        $parsed = $this->parsed($file);
        [$groups, $errors] = $this->groups($parsed['rows'], $parsed['has_variant_active']);
        $imported = 0;
        $savedRows = [];

        if ($groups !== []) {
            DB::transaction(function () use ($groups, &$errors, &$imported, &$savedRows): void {
                [$imported, $savedRows] = $this->saveGroups($groups, $errors);
            });
        }

        usort($errors, fn (array $left, array $right): int => $left['row'] <=> $right['row']);

        return [
            'imported' => $imported,
            'errors' => $errors,
            'logs' => [
                'columns' => $parsed['columns'],
                'rows' => CsvImportResult::logs($parsed['rows'], $parsed['columns'], $errors, $savedRows),
            ],
        ];
    }

    /**
     * @return array{columns: list<string>, rows: list<array{number: int, cells: array<string, string>}>, has_variant_active: bool}
     */
    private function parsed(UploadedFile $file): array
    {
        $parsed = CsvImportFile::read(
            $file,
            self::REQUIRED_COLUMNS,
            self::OPTIONAL_COLUMNS,
            self::MAX_ROWS,
            'catalog rows',
        );

        return [
            'columns' => CsvImportFile::orderedColumns($parsed['columns'], self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS),
            'rows' => $parsed['rows'],
            'has_variant_active' => array_key_exists('variant_active', $parsed['columns']),
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
     * @return array{0: int, 1: list<int>}
     */
    private function saveGroups(array $groups, array &$errors): array
    {
        $items = $this->existingItems($groups);
        $categoryIds = $this->ensureNames(Category::class, $this->filledNames($groups, 'category_name'));
        $brandIds = $this->ensureNames(Brand::class, $this->filledNames($groups, 'brand_name'));
        [$bySku, $byBarcode] = $this->existingVariants($groups);
        $imported = 0;
        $savedRows = [];
        $newItems = [];
        $newVariants = [];
        $timestamp = now();

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

            $categoryName = $group['category_name'];

            if (! $item instanceof CatalogItem && $group['type'] === CatalogItemType::Product->value && ($categoryName === null || $categoryName === '')) {
                $errors[] = ['row' => $group['row'], 'message' => 'Enter a category.'];

                continue;
            }

            if (! $item instanceof CatalogItem) {
                $newItems[] = [
                    'key' => $key,
                    'group' => $group,
                    'variants' => $accepted,
                ];
                $imported += count($accepted);
                array_push($savedRows, ...array_column($accepted, 'row'));

                continue;
            }

            $this->saveItem($item, $group, $categoryIds, $brandIds);
            $imported += count($accepted);
            array_push($savedRows, ...array_column($accepted, 'row'));
            $this->collectNewVariants($item->id, $accepted, $bySku, $newVariants, $timestamp);
        }

        $this->insertItems($newItems, $categoryIds, $brandIds, $newVariants, $timestamp);

        foreach (array_chunk($newVariants, 500) as $chunk) {
            CatalogItemVariant::query()->insert($chunk);
        }

        return [$imported, $savedRows];
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, int>  $categoryIds
     * @param  array<string, int>  $brandIds
     */
    private function saveItem(CatalogItem $item, array $group, array $categoryIds, array $brandIds): void
    {
        $categoryId = $this->nameId($group['category_name'], $categoryIds);
        $brandId = $this->nameId($group['brand_name'], $brandIds);

        $item->name = $group['name'];

        if ($group['description'] !== null) {
            $item->description = $group['description'];
        }

        if ($group['is_active'] !== null) {
            $item->is_active = $group['is_active'];
        }

        if ($categoryId !== null) {
            $item->category_id = $categoryId;
        }

        if ($brandId !== null) {
            $item->brand_id = $brandId;
        }

        $item->save();
    }

    /**
     * @param  list<array{key: string, group: array<string, mixed>, variants: list<array<string, mixed>>}>  $newItems
     * @param  array<string, int>  $categoryIds
     * @param  array<string, int>  $brandIds
     * @param  list<array<string, mixed>>  $newVariants
     */
    private function insertItems(array $newItems, array $categoryIds, array $brandIds, array &$newVariants, Carbon $timestamp): void
    {
        if ($newItems === []) {
            return;
        }

        $rows = [];

        foreach ($newItems as $newItem) {
            $group = $newItem['group'];
            $rows[] = [
                'type' => $group['type'],
                'name' => $group['name'],
                'description' => $group['description'],
                'category_id' => $this->nameId($group['category_name'], $categoryIds),
                'brand_id' => $this->nameId($group['brand_name'], $brandIds),
                'is_active' => $group['is_active'] ?? true,
                'has_branch_pricing' => false,
                'allows_preorder' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            CatalogItem::query()->insert($chunk);
        }

        $created = $this->existingItems(array_map(
            fn (array $newItem): array => [
                'type' => $newItem['group']['type'],
                'name' => $newItem['group']['name'],
            ],
            $newItems,
        ));

        foreach ($newItems as $newItem) {
            $item = $created[$newItem['key']];

            foreach ($newItem['variants'] as $variant) {
                $newVariants[] = $this->variantRow($item->id, $variant, $timestamp);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $accepted
     * @param  Collection<string, CatalogItemVariant>  $bySku
     * @param  list<array<string, mixed>>  $newVariants
     */
    private function collectNewVariants(int $itemId, array $accepted, Collection $bySku, array &$newVariants, Carbon $timestamp): void
    {
        foreach ($accepted as $variant) {
            $owner = $variant['sku'] !== null ? $bySku->get($variant['sku']) : null;

            if ($owner instanceof CatalogItemVariant && $owner->catalog_item_id === $itemId) {
                $changes = [
                    'name' => $variant['name'],
                    'price' => $variant['price'],
                ];

                if ($variant['barcode'] !== null) {
                    $changes['barcode'] = $variant['barcode'];
                }

                if ($variant['is_active'] !== null) {
                    $changes['is_active'] = $variant['is_active'];
                }

                $owner->update($changes);

                continue;
            }

            $newVariants[] = $this->variantRow($itemId, $variant, $timestamp);
        }
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    private function variantRow(int $itemId, array $variant, Carbon $timestamp): array
    {
        return [
            'catalog_item_id' => $itemId,
            'name' => $variant['name'],
            'sku' => $variant['sku'],
            'barcode' => $variant['barcode'],
            'price' => $variant['price'],
            'is_active' => $variant['is_active'] ?? true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  array<string, int>  $ids
     */
    private function nameId(mixed $name, array $ids): ?int
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return $ids[Str::lower($name)];
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return array<string, CatalogItem>
     */
    private function existingItems(array $groups): array
    {
        $names = [];
        $types = [];

        foreach ($groups as $group) {
            $names[Str::lower($group['name'])] = true;
            $types[$group['type']] = true;
        }

        $items = CatalogItem::query()
            ->whereIn('type', array_keys($types))
            ->where(function ($query) use ($names): void {
                foreach (array_chunk(array_keys($names), 500) as $chunk) {
                    $query->orWhereIn(DB::raw('lower(name)'), $chunk);
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
     * @return array<string, int>
     */
    private function ensureNames(string $model, array $names): array
    {
        $ids = [];

        foreach ($model::query()->orderBy('id')->get(['id', 'name']) as $record) {
            $key = Str::lower($record->name);

            if (! isset($ids[$key])) {
                $ids[$key] = $record->id;
            }
        }

        foreach ($names as $name) {
            $key = Str::lower($name);

            if (isset($ids[$key])) {
                continue;
            }

            $ids[$key] = $model::query()->create(['name' => $name])->id;
        }

        return $ids;
    }

    private function toOptionalBoolean(string $value): ?bool
    {
        if ($value === '') {
            return null;
        }

        return in_array(Str::lower($value), ['1', 'true', 'yes', 'active'], true);
    }
}
