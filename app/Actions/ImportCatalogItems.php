<?php

namespace App\Actions;

use App\Models\CatalogItem;
use App\Support\CatalogItemRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportCatalogItems
{
    public const MAX_ROWS = 200;

    /**
     * @var list<string>
     */
    private const COLUMNS = [
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
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file could not be read.',
            ]);
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty.',
                ]);
            }

            $header = array_map(fn (?string $column): string => Str::of((string) $column)->trim()->lower()->toString(), $header);

            if ($header !== self::COLUMNS) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV must include these columns in order: '.implode(', ', self::COLUMNS).'.',
                ]);
            }

            $errors = [];
            $payloads = [];
            $seenSkus = [];
            $seenBarcodes = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber - 1 > self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'file' => 'Upload at most '.self::MAX_ROWS.' catalog rows at a time.',
                    ]);
                }

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $attributes = $this->attributesFromRow($row);
                $sku = $attributes['sku'];
                $barcode = $attributes['barcode'];

                if ($sku !== null && in_array($sku, $seenSkus, true)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'This SKU is duplicated in the file.',
                    ];

                    continue;
                }

                if ($barcode !== null && in_array($barcode, $seenBarcodes, true)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'This barcode is duplicated in the file.',
                    ];

                    continue;
                }

                $validator = Validator::make($attributes, CatalogItemRules::importRow(), CatalogItemRules::messages());

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => $validator->errors()->first(),
                    ];

                    continue;
                }

                if ($sku !== null) {
                    $seenSkus[] = $sku;
                }

                if ($barcode !== null) {
                    $seenBarcodes[] = $barcode;
                }

                $payloads[] = $validator->validated();
            }
        } finally {
            fclose($handle);
        }

        $saveCatalogItem = app(SaveCatalogItem::class);

        DB::transaction(function () use ($payloads, $saveCatalogItem): void {
            $items = [];

            foreach ($payloads as $payload) {
                $key = $payload['type'].'|'.Str::lower($payload['name']);
                $item = $items[$key] ?? CatalogItem::query()
                    ->where('type', $payload['type'])
                    ->where('name', $payload['name'])
                    ->first();

                $variant = [
                    'name' => $payload['variant_name'],
                    'sku' => $payload['sku'] ?? null,
                    'barcode' => $payload['barcode'] ?? null,
                    'price' => $payload['price'],
                    'is_active' => $payload['is_active'],
                ];

                $existingVariants = $item
                    ? $item->variants()->with('branchPrices')->get()->map(fn ($existing): array => [
                        'id' => $existing->id,
                        'name' => $existing->name,
                        'sku' => $existing->sku,
                        'barcode' => $existing->barcode,
                        'price' => $existing->price,
                        'is_active' => $existing->is_active,
                        'branch_prices' => $existing->branchPrices->map(fn ($price): array => [
                            'branch_id' => $price->branch_id,
                            'price' => $price->price,
                        ])->all(),
                    ])->all()
                    : [];

                $items[$key] = $saveCatalogItem->handle([
                    'type' => $payload['type'],
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                    'category_name' => $payload['category_name'] ?? null,
                    'brand_name' => $payload['brand_name'] ?? null,
                    'is_active' => $payload['is_active'],
                    'has_branch_pricing' => $item?->has_branch_pricing ?? false,
                    'modifier_ids' => $item?->modifiers()->pluck('modifiers.id')->all() ?? [],
                    'modifier_prices' => $item
                        ? $item->modifierOptionPrices()->with('branchPrices')->get()->map(fn ($price): array => [
                            'modifier_id' => $price->modifier_id,
                            'option' => $price->option,
                            'price' => $price->price,
                            'branch_prices' => $price->branchPrices->map(fn ($branchPrice): array => [
                                'branch_id' => $branchPrice->branch_id,
                                'price' => $branchPrice->price,
                            ])->all(),
                        ])->all()
                        : [],
                    'variants' => [...$existingVariants, $variant],
                ], $item);
            }
        });

        return [
            'imported' => count($payloads),
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function attributesFromRow(array $row): array
    {
        $values = [];

        foreach (self::COLUMNS as $index => $column) {
            $values[$column] = Str::of((string) ($row[$index] ?? ''))->trim()->toString();
        }

        $sku = $values['sku'] === '' ? null : $values['sku'];
        $barcode = preg_replace('/[\x00-\x1F\x7F]/', '', $values['barcode']) ?: null;

        return [
            'type' => Str::lower($values['type']),
            'name' => $values['name'],
            'description' => $values['description'] === '' ? null : $values['description'],
            'category_name' => $values['category'] === '' ? null : $values['category'],
            'brand_name' => $values['brand'] === '' ? null : $values['brand'],
            'variant_name' => $values['variant_name'],
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => $values['price'],
            'is_active' => $this->toBoolean($values['is_active']),
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (Str::of((string) $value)->trim()->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function toBoolean(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return in_array(Str::lower($value), ['1', 'true', 'yes', 'active'], true);
    }
}
