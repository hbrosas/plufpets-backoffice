<?php

namespace App\Support;

use App\Models\CatalogItem;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockConversion;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogItemSupplies
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(CatalogItem $item): Collection
    {
        $variantIds = $item->variants()->pluck('id');

        if ($variantIds->isEmpty()) {
            return collect();
        }

        $purchases = PurchaseOrderReceiptItem::query()
            ->with([
                'receipt:id,purchase_order_id,received_at',
                'purchaseOrderItem:id,catalog_item_variant_id,unit_cost',
                'purchaseOrderItem.variant:id,name,sku,barcode,price,catalog_item_id',
            ])
            ->whereHas('purchaseOrderItem', fn ($query) => $query->whereIn('catalog_item_variant_id', $variantIds))
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseOrderReceiptItem $row): array => $this->fromPurchase($row));

        $conversions = StockConversion::query()
            ->with(['toVariant:id,name,sku,barcode,price,catalog_item_id'])
            ->whereIn('to_catalog_item_variant_id', $variantIds)
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockConversion $row): array => $this->fromConversion($row));

        return $purchases
            ->concat($conversions)
            ->sortByDesc(fn (array $supply): array => [$supply['sort_at'], $supply['id']])
            ->values()
            ->map(function (array $supply): array {
                unset($supply['sort_at']);

                return $supply;
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function find(CatalogItem $item, string $key): array
    {
        if (! preg_match('/^(purchase|conversion)-(\d+)$/', $key, $matches)) {
            throw new NotFoundHttpException;
        }

        $variantIds = $item->variants()->pluck('id');
        $id = (int) $matches[2];

        if ($matches[1] === 'purchase') {
            $row = PurchaseOrderReceiptItem::query()
                ->with([
                    'receipt:id,purchase_order_id,received_at',
                    'purchaseOrderItem:id,catalog_item_variant_id,unit_cost',
                    'purchaseOrderItem.variant:id,name,sku,barcode,price,catalog_item_id',
                ])
                ->whereKey($id)
                ->whereHas('purchaseOrderItem', fn ($query) => $query->whereIn('catalog_item_variant_id', $variantIds))
                ->first();

            if ($row === null) {
                throw new NotFoundHttpException;
            }

            return $this->fromPurchase($row);
        }

        $row = StockConversion::query()
            ->with(['toVariant:id,name,sku,barcode,price,catalog_item_id'])
            ->whereKey($id)
            ->whereIn('to_catalog_item_variant_id', $variantIds)
            ->first();

        if ($row === null) {
            throw new NotFoundHttpException;
        }

        return $this->fromConversion($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromPurchase(PurchaseOrderReceiptItem $row): array
    {
        $variant = $row->purchaseOrderItem?->variant;

        return [
            'id' => 'purchase-'.$row->id,
            'kind' => 'purchase',
            'supply_date' => $this->formatDate($row->receipt?->received_at),
            'packed_date' => $this->formatShortDate($row->receipt?->received_at),
            'price' => Peso::format($row->purchaseOrderItem?->unit_cost),
            'retail_price' => Peso::format($variant?->price),
            'quantity' => $row->quantity,
            'variant' => $variant?->name,
            'variant_id' => $variant?->id,
            'barcode' => $variant?->barcode ?: ($variant?->sku ?: (string) $variant?->id),
            'sort_at' => $row->receipt?->received_at?->timestamp ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromConversion(StockConversion $row): array
    {
        $variant = $row->toVariant;

        return [
            'id' => 'conversion-'.$row->id,
            'kind' => 'conversion',
            'supply_date' => $this->formatDate($row->created_at),
            'packed_date' => $this->formatShortDate($row->created_at),
            'price' => $row->unit_cost === null ? '—' : Peso::format($row->unit_cost),
            'retail_price' => Peso::format($variant?->price),
            'quantity' => $row->to_quantity,
            'variant' => $variant?->name,
            'variant_id' => $variant?->id,
            'barcode' => $variant?->barcode ?: ($variant?->sku ?: (string) $variant?->id),
            'sort_at' => $row->created_at?->timestamp ?? 0,
        ];
    }

    private function formatDate(mixed $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('D, M j, Y g:i A');
    }

    private function formatShortDate(mixed $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('M j, Y');
    }
}
