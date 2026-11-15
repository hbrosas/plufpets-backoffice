<?php

namespace App\Actions;

use App\Models\CatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateCatalogItem
{
    public function handle(CatalogItem $catalogItem): CatalogItem
    {
        return DB::transaction(function () use ($catalogItem): CatalogItem {
            $catalogItem->load([
                'variants.branchPrices',
                'modifiers',
                'modifierOptionPrices.branchPrices',
            ]);

            $copy = $catalogItem->replicate();
            $copy->name = $this->copyName($catalogItem);
            $copy->save();

            $copy->modifiers()->sync($catalogItem->modifiers->pluck('id')->all());

            foreach ($catalogItem->variants as $variant) {
                $variantCopy = $variant->replicate(['sku', 'barcode', 'image_path']);
                $variantCopy->catalog_item_id = $copy->id;
                $variantCopy->sku = null;
                $variantCopy->barcode = null;
                $variantCopy->image_path = $this->copyImage($variant->image_path);
                $variantCopy->save();

                foreach ($variant->branchPrices as $price) {
                    $priceCopy = $price->replicate();
                    $priceCopy->catalog_item_variant_id = $variantCopy->id;
                    $priceCopy->save();
                }
            }

            foreach ($catalogItem->modifierOptionPrices as $price) {
                $priceCopy = $price->replicate();
                $priceCopy->catalog_item_id = $copy->id;
                $priceCopy->save();

                foreach ($price->branchPrices as $branchPrice) {
                    $branchCopy = $branchPrice->replicate();
                    $branchCopy->catalog_item_modifier_option_price_id = $priceCopy->id;
                    $branchCopy->save();
                }
            }

            return $copy;
        });
    }

    private function copyName(CatalogItem $catalogItem): string
    {
        $suffix = ' (copy)';
        $name = $this->fitName($catalogItem->name, $suffix);
        $count = 2;

        while (CatalogItem::query()->where('name', $name)->exists()) {
            $suffix = ' (copy '.$count.')';
            $name = $this->fitName($catalogItem->name, $suffix);
            $count++;
        }

        return $name;
    }

    private function fitName(string $name, string $suffix): string
    {
        $limit = 255 - mb_strlen($suffix);

        return mb_substr($name, 0, max(1, $limit)).$suffix;
    }

    private function copyImage(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = 'catalog-images/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
        Storage::disk('public')->copy($path, $target);

        return $target;
    }
}
