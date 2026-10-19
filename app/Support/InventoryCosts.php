<?php

namespace App\Support;

use App\Models\PurchaseOrderItem;
use App\Models\StockConversion;

class InventoryCosts
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, float>
     */
    public function latestUnitCosts(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        if ($variantIds === []) {
            return [];
        }

        $purchases = PurchaseOrderItem::query()
            ->whereIn('catalog_item_variant_id', $variantIds)
            ->orderByDesc('id')
            ->get(['id', 'catalog_item_variant_id', 'unit_cost', 'created_at'])
            ->unique('catalog_item_variant_id')
            ->keyBy('catalog_item_variant_id');

        $conversions = StockConversion::query()
            ->whereIn('to_catalog_item_variant_id', $variantIds)
            ->whereNotNull('unit_cost')
            ->orderByDesc('id')
            ->get(['id', 'to_catalog_item_variant_id', 'unit_cost', 'created_at'])
            ->unique('to_catalog_item_variant_id')
            ->keyBy('to_catalog_item_variant_id');

        $costs = [];

        foreach ($variantIds as $variantId) {
            $purchase = $purchases->get($variantId);
            $conversion = $conversions->get($variantId);

            if ($purchase === null && $conversion === null) {
                continue;
            }

            if ($purchase !== null && ($conversion === null || $purchase->created_at->gte($conversion->created_at))) {
                $costs[$variantId] = (float) $purchase->unit_cost;

                continue;
            }

            $costs[$variantId] = (float) $conversion->unit_cost;
        }

        return $costs;
    }

    public function latestUnitCost(int $variantId): ?float
    {
        return $this->latestUnitCosts([$variantId])[$variantId] ?? null;
    }
}
