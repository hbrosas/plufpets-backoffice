<?php

namespace App\Actions;

use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Modifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaveCatalogItem
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?CatalogItem $catalogItem = null): CatalogItem
    {
        return DB::transaction(function () use ($attributes, $catalogItem): CatalogItem {
            $item = $catalogItem ?? new CatalogItem;

            $item->fill([
                'type' => $attributes['type'],
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'],
                'has_branch_pricing' => $attributes['has_branch_pricing'] ?? false,
                'category_id' => $this->categoryId($attributes['category_name'] ?? null),
                'brand_id' => $this->brandId($attributes['brand_name'] ?? null),
            ]);

            $item->save();

            $this->syncVariants($item, $attributes['variants'], (bool) ($attributes['has_branch_pricing'] ?? false));
            $this->syncModifiers(
                $item,
                $attributes['modifier_ids'] ?? [],
                $attributes['modifier_prices'] ?? [],
                (bool) ($attributes['has_branch_pricing'] ?? false),
            );

            return $item->refresh();
        });
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return Category::query()->firstOrCreate(['name' => $name])->id;
    }

    private function brandId(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        return Brand::query()->firstOrCreate(['name' => $name])->id;
    }

    /**
     * @param  list<array{id?: int|null, name: string, sku: string|null, barcode: string|null, price: mixed, is_active: bool, image?: UploadedFile|null, remove_image?: bool, branch_prices?: list<array{branch_id: int, price: mixed}>}>  $variants
     */
    private function syncVariants(CatalogItem $item, array $variants, bool $hasBranchPricing): void
    {
        $keptIds = [];

        foreach ($variants as $variant) {
            $record = isset($variant['id'])
                ? $item->variants()->whereKey($variant['id'])->first()
                : null;

            $record ??= new CatalogItemVariant(['catalog_item_id' => $item->id]);

            $record->fill([
                'name' => $variant['name'],
                'sku' => $variant['sku'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'price' => $variant['price'],
                'is_active' => $variant['is_active'],
            ]);
            $record->save();
            $this->syncVariantImage($record, $variant);
            $this->syncBranchPrices($record, $hasBranchPricing, $variant['branch_prices'] ?? []);

            $keptIds[] = $record->id;
        }

        $item->variants()->whereNotIn('id', $keptIds)->get()->each(function (CatalogItemVariant $variant): void {
            $this->deleteVariantImage($variant);
            $variant->delete();
        });
    }

    /**
     * @param  array{image?: UploadedFile|null, remove_image?: bool}  $variant
     */
    private function syncVariantImage(CatalogItemVariant $record, array $variant): void
    {
        $image = $variant['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $this->deleteVariantImage($record);
            $record->image_path = $image->store('catalog-images', 'public');
            $record->save();

            return;
        }

        if (($variant['remove_image'] ?? false) && $record->image_path) {
            $this->deleteVariantImage($record);
            $record->image_path = null;
            $record->save();
        }
    }

    private function deleteVariantImage(CatalogItemVariant $variant): void
    {
        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }
    }

    /**
     * @param  list<array{branch_id: int, price: mixed}>  $prices
     */
    private function syncBranchPrices(CatalogItemVariant $variant, bool $hasBranchPricing, array $prices): void
    {
        if (! $hasBranchPricing) {
            $variant->branchPrices()->delete();

            return;
        }

        $keptBranchIds = [];

        foreach ($prices as $price) {
            if ($price['price'] === null) {
                $variant->branchPrices()->where('branch_id', $price['branch_id'])->delete();

                continue;
            }

            $variant->branchPrices()->updateOrCreate(
                ['branch_id' => $price['branch_id']],
                ['price' => $price['price']],
            );

            $keptBranchIds[] = $price['branch_id'];
        }

        $variant->branchPrices()->whereNotIn('branch_id', $keptBranchIds === [] ? [0] : $keptBranchIds)->delete();
    }

    /**
     * @param  list<int>  $modifierIds
     * @param  list<array{modifier_id: int, option: string, price: mixed, branch_prices?: list<array{branch_id: int, price: mixed}>}>  $prices
     */
    private function syncModifiers(CatalogItem $item, array $modifierIds, array $prices, bool $hasBranchPricing): void
    {
        $item->modifiers()->sync($modifierIds);

        $submitted = collect($prices)->keyBy(
            fn (array $row): string => $row['modifier_id'].'|'.$row['option'],
        );

        $modifiers = Modifier::query()
            ->whereIn('id', $modifierIds === [] ? [0] : $modifierIds)
            ->get(['id', 'options']);

        $keptIds = [];

        foreach ($modifiers as $modifier) {
            foreach ($modifier->options ?? [] as $option) {
                $row = $submitted->get($modifier->id.'|'.$option);
                $record = $item->modifierOptionPrices()->updateOrCreate(
                    [
                        'modifier_id' => $modifier->id,
                        'option' => $option,
                    ],
                    [
                        'price' => $row['price'] ?? 0,
                    ],
                );

                $this->syncModifierOptionBranchPrices($record, $hasBranchPricing, $row['branch_prices'] ?? []);
                $keptIds[] = $record->id;
            }
        }

        $item->modifierOptionPrices()->whereNotIn('id', $keptIds === [] ? [0] : $keptIds)->get()->each->delete();
    }

    /**
     * @param  list<array{branch_id: int, price: mixed}>  $prices
     */
    private function syncModifierOptionBranchPrices(
        CatalogItemModifierOptionPrice $optionPrice,
        bool $hasBranchPricing,
        array $prices,
    ): void {
        if (! $hasBranchPricing) {
            $optionPrice->branchPrices()->delete();

            return;
        }

        $keptBranchIds = [];

        foreach ($prices as $price) {
            if ($price['price'] === null) {
                $optionPrice->branchPrices()->where('branch_id', $price['branch_id'])->delete();

                continue;
            }

            $optionPrice->branchPrices()->updateOrCreate(
                ['branch_id' => $price['branch_id']],
                ['price' => $price['price']],
            );

            $keptBranchIds[] = $price['branch_id'];
        }

        $optionPrice->branchPrices()->whereNotIn('branch_id', $keptBranchIds === [] ? [0] : $keptBranchIds)->delete();
    }
}
