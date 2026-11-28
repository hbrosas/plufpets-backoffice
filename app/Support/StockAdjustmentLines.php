<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PosSaleItem;
use Illuminate\Support\Collection;

class StockAdjustmentLines
{
    public function __construct(private InventoryCosts $inventoryCosts) {}

    /**
     * Products in a branch for a bulk adjustment, with lifetime sold quantity and profit.
     *
     * @return list<array{
     *     variant_id: int,
     *     product: string,
     *     variant: string,
     *     sku: string|null,
     *     quantity: int,
     *     reserved: int,
     *     sold: int,
     *     profit: string,
     *     profit_amount: float|null,
     *     retail_price: string,
     *     retail_price_amount: float|null
     * }>
     */
    public function for(string $scope, ?int $brandId, ?int $categoryId, int $branchId): array
    {
        if ($scope === 'brand' && $brandId === null) {
            return [];
        }

        if ($scope === 'category' && $categoryId === null) {
            return [];
        }

        $variants = CatalogItemVariant::query()
            ->with([
                'catalogItem:id,name,type,brand_id,category_id',
                'branchPrices' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->whereHas('catalogItem', function ($query) use ($scope, $brandId, $categoryId): void {
                $query->where('type', CatalogItemType::Product);

                if ($scope === 'brand') {
                    $query->where('brand_id', $brandId);
                }

                if ($scope === 'category') {
                    $query->where('category_id', $categoryId);
                }
            })
            ->orderBy('id')
            ->get();

        if ($variants->isEmpty()) {
            return [];
        }

        $variantIds = $variants->modelKeys();
        $inventories = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->whereIn('catalog_item_variant_id', $variantIds)
            ->get()
            ->keyBy('catalog_item_variant_id');
        $sales = PosSaleItem::query()
            ->whereIn('catalog_item_variant_id', $variantIds)
            ->where('is_free', false)
            ->whereHas('sale', fn ($query) => $query->whereNull('refunded_at'))
            ->groupBy('catalog_item_variant_id')
            ->selectRaw('catalog_item_variant_id, SUM(quantity) as sold_quantity, SUM(line_total) as revenue')
            ->get()
            ->keyBy('catalog_item_variant_id');
        $costs = $this->inventoryCosts->latestUnitCosts($variantIds);

        return $variants
            ->map(function (CatalogItemVariant $variant) use ($inventories, $sales, $costs, $branchId): array {
                $inventory = $inventories->get($variant->id);
                $sale = $sales->get($variant->id);
                $sold = (int) ($sale->sold_quantity ?? 0);
                $revenue = (float) ($sale->revenue ?? 0);
                $cost = $costs[$variant->id] ?? null;

                if ($sold === 0) {
                    $profit = 0.0;
                } elseif ($cost === null) {
                    $profit = null;
                } else {
                    $profit = round($revenue - ($cost * $sold), 2);
                }
                $retail = $this->retailPrice($variant, $branchId);

                return [
                    'variant_id' => $variant->id,
                    'product' => (string) ($variant->catalogItem?->name ?? 'Product'),
                    'variant' => (string) $variant->name,
                    'sku' => $variant->sku,
                    'quantity' => (int) ($inventory->quantity ?? 0),
                    'reserved' => (int) ($inventory->reserved ?? 0),
                    'sold' => $sold,
                    'profit' => $profit === null ? '—' : Peso::format($profit),
                    'profit_amount' => $profit,
                    'retail_price' => $retail === null ? '—' : Peso::format($retail),
                    'retail_price_amount' => $retail,
                ];
            })
            ->sortBy(fn (array $row): array => [$row['product'], $row['variant'], $row['variant_id']])
            ->values()
            ->all();
    }

    public function retailPrice(CatalogItemVariant $variant, int $branchId): ?float
    {
        $branchPrice = $variant->relationLoaded('branchPrices')
            ? $variant->branchPrices->firstWhere('branch_id', $branchId)?->price
            : $variant->branchPrices()->where('branch_id', $branchId)->value('price');

        if ($branchPrice !== null) {
            return (float) $branchPrice;
        }

        return $variant->price === null ? null : (float) $variant->price;
    }

    /**
     * @param  list<int>  $variantIds
     * @return Collection<int, CatalogItemVariant>
     */
    public function variantsFor(array $variantIds, int $branchId): Collection
    {
        return CatalogItemVariant::query()
            ->with([
                'catalogItem:id,name,type,brand_id,category_id',
                'branchPrices' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->whereKey($variantIds)
            ->get()
            ->keyBy('id');
    }
}
