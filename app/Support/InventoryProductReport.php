<?php

namespace App\Support;

use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockAssignment;
use App\Models\StockConversion;
use App\Models\StockTransfer;
use Illuminate\Support\Collection;

class InventoryProductReport
{
    public function __construct(public InventoryCosts $inventoryCosts) {}

    /**
     * @return array{
     *     product: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     locations: list<array{name: string, quantity: int}>,
     *     purchases: list<array<string, mixed>>,
     *     sales: list<array<string, mixed>>,
     *     history: list<array<string, mixed>>,
     *     repack_dates: list<string>
     * }
     */
    public function for(CatalogItemVariant $variant): array
    {
        $variant->loadMissing(['catalogItem:id,name']);

        $inventories = BranchInventory::query()
            ->with('branch:id,name')
            ->where('catalog_item_variant_id', $variant->id)
            ->orderBy('id')
            ->get();

        $receiptItems = PurchaseOrderReceiptItem::query()
            ->with([
                'receipt:id,purchase_order_id,delivery_receipt_number,sales_invoice_number,received_at,received_by_name',
                'receipt.purchaseOrder:id,number,supplier_id',
                'receipt.purchaseOrder.supplier:id,name',
                'purchaseOrderItem:id,catalog_item_variant_id,unit_cost',
            ])
            ->whereHas('purchaseOrderItem', fn ($query) => $query->where('catalog_item_variant_id', $variant->id))
            ->orderByDesc('id')
            ->get();

        $assignments = StockAssignment::query()
            ->with([
                'branch:id,name',
                'purchaseOrderItem:id,purchase_order_id,catalog_item_variant_id',
                'purchaseOrderItem.purchaseOrder:id,number',
            ])
            ->whereHas('purchaseOrderItem', fn ($query) => $query->where('catalog_item_variant_id', $variant->id))
            ->orderByDesc('id')
            ->get();

        $transfers = StockTransfer::query()
            ->with(['fromBranch:id,name', 'toBranch:id,name'])
            ->where('catalog_item_variant_id', $variant->id)
            ->orderByDesc('id')
            ->get();

        $conversionsOut = StockConversion::query()
            ->with(['branch:id,name', 'toVariant:id,name,catalog_item_id', 'toVariant.catalogItem:id,name'])
            ->where('from_catalog_item_variant_id', $variant->id)
            ->orderByDesc('id')
            ->get();

        $conversionsIn = StockConversion::query()
            ->with(['branch:id,name', 'fromVariant:id,name,catalog_item_id', 'fromVariant.catalogItem:id,name'])
            ->where('to_catalog_item_variant_id', $variant->id)
            ->orderByDesc('id')
            ->get();

        $purchasePrice = $this->inventoryCosts->latestUnitCost($variant->id);
        $onHand = (int) $inventories->sum('quantity');
        $purchased = (int) $receiptItems->sum('quantity');
        $convertedIn = (int) $conversionsIn->sum('to_quantity');
        $convertedOut = (int) $conversionsOut->sum('from_quantity');

        $purchases = $receiptItems->map(fn (PurchaseOrderReceiptItem $item): array => [
            'id' => $item->id,
            'occurred_at' => $this->formatDate($item->receipt?->received_at),
            'sort_at' => $item->receipt?->received_at?->timestamp ?? 0,
            'quantity' => $item->quantity,
            'reference' => $item->receipt?->purchaseOrder?->number,
            'supplier' => $item->receipt?->purchaseOrder?->supplier?->name,
            'delivery_receipt' => $item->receipt?->delivery_receipt_number,
            'sales_invoice' => $item->receipt?->sales_invoice_number,
            'unit_cost' => Peso::format($item->purchaseOrderItem?->unit_cost),
            'received_by' => $item->receipt?->received_by_name,
        ])->values();

        $repackDates = $conversionsIn
            ->map(fn (StockConversion $conversion): ?string => $this->formatShortDate($conversion->created_at))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $history = collect()
            ->concat($this->purchaseHistory($purchases))
            ->concat($this->assignmentHistory($assignments))
            ->concat($this->transferHistory($transfers))
            ->concat($this->conversionInHistory($conversionsIn))
            ->concat($this->conversionOutHistory($conversionsOut))
            ->sortByDesc(fn (array $row): array => [$row['sort_at'], $row['id']])
            ->values()
            ->map(function (array $row): array {
                unset($row['sort_at']);

                return $row;
            })
            ->all();

        $locations = $inventories
            ->map(fn (BranchInventory $inventory): array => [
                'name' => $inventory->branch?->name ?? 'Unknown',
                'quantity' => $inventory->quantity,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'product' => [
                'id' => $variant->id,
                'name' => $variant->catalogItem?->name,
                'variant' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'purchase_price' => $purchasePrice === null ? '—' : Peso::format($purchasePrice),
                'retail_price' => Peso::format($variant->price),
                'markup' => $this->formatMarkup($purchasePrice, $variant->price),
            ],
            'summary' => [
                'on_hand' => $onHand,
                'value' => Peso::format($purchasePrice === null ? 0 : $purchasePrice * $onHand),
                'purchased' => $purchased,
                'sold' => 0,
                'converted_in' => $convertedIn,
                'converted_out' => $convertedOut,
                'last_purchased' => data_get($purchases->first(), 'occurred_at'),
                'last_repacked' => $repackDates[0] ?? null,
            ],
            'locations' => $locations,
            'purchases' => $purchases->map(fn (array $row): array => [
                'id' => $row['id'],
                'occurred_at' => $row['occurred_at'],
                'quantity' => $row['quantity'],
                'reference' => $row['reference'],
                'supplier' => $row['supplier'],
                'delivery_receipt' => $row['delivery_receipt'],
                'sales_invoice' => $row['sales_invoice'],
                'unit_cost' => $row['unit_cost'],
                'received_by' => $row['received_by'],
            ])->all(),
            'sales' => [],
            'history' => $history,
            'repack_dates' => $repackDates,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $purchases
     * @return Collection<int, array<string, mixed>>
     */
    private function purchaseHistory(Collection $purchases): Collection
    {
        return $purchases->map(fn (array $purchase): array => [
            'id' => 'purchase-'.$purchase['id'],
            'sort_at' => $purchase['sort_at'],
            'occurred_at' => $purchase['occurred_at'],
            'type' => 'Purchase',
            'quantity' => $purchase['quantity'],
            'location' => $purchase['supplier'] ?? '—',
            'reference' => trim(($purchase['reference'] ?? '').' · '.($purchase['delivery_receipt'] ?? ''), ' ·'),
            'detail' => $purchase['unit_cost'].' each',
        ]);
    }

    /**
     * @param  Collection<int, StockAssignment>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function assignmentHistory(Collection $assignments): Collection
    {
        return $assignments->map(fn (StockAssignment $assignment): array => [
            'id' => 'assignment-'.$assignment->id,
            'sort_at' => $assignment->created_at?->timestamp ?? 0,
            'occurred_at' => $this->formatDate($assignment->created_at),
            'type' => 'Assignment',
            'quantity' => $assignment->quantity,
            'location' => $assignment->branch?->name ?? '—',
            'reference' => $assignment->purchaseOrderItem?->purchaseOrder?->number,
            'detail' => 'Assigned to branch',
        ]);
    }

    /**
     * @param  Collection<int, StockTransfer>  $transfers
     * @return Collection<int, array<string, mixed>>
     */
    private function transferHistory(Collection $transfers): Collection
    {
        return $transfers->map(fn (StockTransfer $transfer): array => [
            'id' => 'transfer-'.$transfer->id,
            'sort_at' => $transfer->created_at?->timestamp ?? 0,
            'occurred_at' => $this->formatDate($transfer->created_at),
            'type' => 'Transfer',
            'quantity' => $transfer->quantity,
            'location' => ($transfer->fromBranch?->name ?? '—').' → '.($transfer->toBranch?->name ?? '—'),
            'reference' => null,
            'detail' => 'Moved between branches',
        ]);
    }

    /**
     * @param  Collection<int, StockConversion>  $conversions
     * @return Collection<int, array<string, mixed>>
     */
    private function conversionInHistory(Collection $conversions): Collection
    {
        return $conversions->map(function (StockConversion $conversion): array {
            $source = $conversion->fromVariant?->catalogItem?->name
                .($conversion->fromVariant?->name && $conversion->fromVariant->name !== 'Default' ? ' · '.$conversion->fromVariant->name : '');

            return [
                'id' => 'conversion-in-'.$conversion->id,
                'sort_at' => $conversion->created_at?->timestamp ?? 0,
                'occurred_at' => $this->formatDate($conversion->created_at),
                'type' => 'Repack',
                'quantity' => $conversion->to_quantity,
                'location' => $conversion->branch?->name ?? '—',
                'reference' => $this->formatShortDate($conversion->created_at),
                'detail' => 'Converted from '.$source,
            ];
        });
    }

    /**
     * @param  Collection<int, StockConversion>  $conversions
     * @return Collection<int, array<string, mixed>>
     */
    private function conversionOutHistory(Collection $conversions): Collection
    {
        return $conversions->map(function (StockConversion $conversion): array {
            $destination = $conversion->toVariant?->catalogItem?->name
                .($conversion->toVariant?->name && $conversion->toVariant->name !== 'Default' ? ' · '.$conversion->toVariant->name : '');

            return [
                'id' => 'conversion-out-'.$conversion->id,
                'sort_at' => $conversion->created_at?->timestamp ?? 0,
                'occurred_at' => $this->formatDate($conversion->created_at),
                'type' => 'Conversion',
                'quantity' => -$conversion->from_quantity,
                'location' => $conversion->branch?->name ?? '—',
                'reference' => $this->formatShortDate($conversion->created_at),
                'detail' => 'Converted into '.$destination.' ('.$conversion->to_quantity.' pieces)',
            ];
        });
    }

    private function formatDate(mixed $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('D, M j, Y g:i A');
    }

    private function formatShortDate(mixed $date): ?string
    {
        return $date?->timezone(config('app.timezone'))->format('M j, Y');
    }

    private function formatMarkup(?float $purchasePrice, mixed $retailPrice): string
    {
        if ($purchasePrice === null || $purchasePrice <= 0.0) {
            return '—';
        }

        $percent = (((float) $retailPrice - $purchasePrice) / $purchasePrice) * 100;

        return rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';
    }
}
