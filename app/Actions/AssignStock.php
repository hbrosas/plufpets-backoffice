<?php

namespace App\Actions;

use App\Models\BranchInventory;
use App\Models\PurchaseOrderItem;
use App\Models\StockAssignment;
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
            $rows = collect($items)->sortBy('purchase_order_item_id')->values();

            foreach ($rows as $row) {
                $this->assignItem($branchId, (int) $row['purchase_order_item_id'], (int) $row['quantity']);
            }
        });
    }

    private function assignItem(int $branchId, int $purchaseOrderItemId, int $quantity): void
    {
        $item = PurchaseOrderItem::query()
            ->with(['assignments', 'variant.catalogItem'])
            ->whereKey($purchaseOrderItemId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($quantity > $item->remainingToAssign()) {
            throw ValidationException::withMessages([
                'items' => 'Only '.$item->remainingToAssign().' units remain to assign for '.$item->variant?->catalogItem?->name.'.',
            ]);
        }

        StockAssignment::query()->create([
            'purchase_order_item_id' => $item->id,
            'branch_id' => $branchId,
            'quantity' => $quantity,
            'selling_price' => $item->variant?->price ?? '0.00',
        ]);

        $inventory = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('catalog_item_variant_id', $item->catalog_item_variant_id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            BranchInventory::query()->create([
                'branch_id' => $branchId,
                'catalog_item_variant_id' => $item->catalog_item_variant_id,
                'quantity' => $quantity,
            ]);
        } else {
            $inventory->update([
                'quantity' => $inventory->quantity + $quantity,
            ]);
        }
    }
}
