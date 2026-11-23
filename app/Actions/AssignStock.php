<?php

namespace App\Actions;

use App\Models\BranchInventory;
use App\Models\PurchaseOrderItem;
use App\Models\StockAssignment;
use App\Support\TestingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignStock
{
    /**
     * @param  list<array{purchase_order_item_id: int, quantity: int}>  $items
     */
    public function handle(int $branchId, array $items): void
    {
        DB::transaction(function () use ($branchId, $items): void {
            $requested = collect($items)
                ->map(fn (array $row): array => [
                    'purchase_order_item_id' => (int) $row['purchase_order_item_id'],
                    'quantity' => (int) $row['quantity'],
                ])
                ->sortBy('purchase_order_item_id')
                ->values();

            $ids = $requested->pluck('purchase_order_item_id')->all();

            $orderItems = PurchaseOrderItem::query()
                ->with(['variant:id,catalog_item_id,name,price', 'variant.catalogItem:id,name'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $assigned = StockAssignment::query()
                ->withoutGlobalScope(TestingMode::SCOPE)
                ->selectRaw('purchase_order_item_id, sum(quantity) as assigned_quantity')
                ->whereIn('purchase_order_item_id', $ids)
                ->groupBy('purchase_order_item_id')
                ->pluck('assigned_quantity', 'purchase_order_item_id');

            $timestamp = now();
            $assignmentRows = [];
            $quantitiesByVariant = [];

            foreach ($requested as $row) {
                $item = $orderItems->get($row['purchase_order_item_id']);

                if (! $item instanceof PurchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Choose a delivered product.',
                    ]);
                }

                $remaining = max($item->received_quantity - (int) ($assigned[$item->id] ?? 0), 0);

                if ($row['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Only '.$remaining.' units remain to assign for '.$item->variant?->catalogItem?->name.'.',
                    ]);
                }

                $assignmentRows[] = [
                    'purchase_order_item_id' => $item->id,
                    'branch_id' => $branchId,
                    'quantity' => $row['quantity'],
                    'selling_price' => $item->variant?->price ?? '0.00',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                $variantId = $item->catalog_item_variant_id;
                $quantitiesByVariant[$variantId] = ($quantitiesByVariant[$variantId] ?? 0) + $row['quantity'];
            }

            StockAssignment::query()->withoutGlobalScope(TestingMode::SCOPE)->insert($assignmentRows);

            $variantIds = array_keys($quantitiesByVariant);
            sort($variantIds);

            $inventories = BranchInventory::query()
                ->where('branch_id', $branchId)
                ->whereIn('catalog_item_variant_id', $variantIds)
                ->orderBy('catalog_item_variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('catalog_item_variant_id');

            $inserts = [];
            $existing = [];

            foreach ($variantIds as $variantId) {
                $quantity = $quantitiesByVariant[$variantId];

                if (! $inventories->has($variantId)) {
                    $inserts[] = [
                        'branch_id' => $branchId,
                        'catalog_item_variant_id' => $variantId,
                        'quantity' => $quantity,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    continue;
                }

                $existing[$variantId] = $quantity;
            }

            if ($existing !== []) {
                $cases = [];

                foreach ($existing as $variantId => $quantity) {
                    $cases[] = 'when '.(int) $variantId.' then quantity + '.(int) $quantity;
                }

                BranchInventory::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('catalog_item_variant_id', array_keys($existing))
                    ->update([
                        'quantity' => DB::raw('case catalog_item_variant_id '.implode(' ', $cases).' else quantity end'),
                    ]);
            }

            if ($inserts !== []) {
                BranchInventory::query()->insert($inserts);
            }
        });
    }
}
