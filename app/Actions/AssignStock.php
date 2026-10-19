<?php

namespace App\Actions;

use App\Models\BranchInventory;
use App\Models\BranchVariantPrice;
use App\Models\PurchaseOrderItem;
use App\Models\StockAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignStock
{
    /**
     * @param  array{purchase_order_item_id: int, branch_id: int, quantity: int, selling_price: mixed}  $attributes
     */
    public function handle(array $attributes): StockAssignment
    {
        return DB::transaction(function () use ($attributes): StockAssignment {
            $item = PurchaseOrderItem::query()
                ->with(['assignments', 'variant.catalogItem'])
                ->whereKey($attributes['purchase_order_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($attributes['quantity'] > $item->remainingToAssign()) {
                throw ValidationException::withMessages([
                    'quantity' => 'Only '.$item->remainingToAssign().' units remain to assign.',
                ]);
            }

            $assignment = StockAssignment::query()->create([
                'purchase_order_item_id' => $item->id,
                'branch_id' => $attributes['branch_id'],
                'quantity' => $attributes['quantity'],
                'selling_price' => $attributes['selling_price'],
            ]);

            $inventory = BranchInventory::query()
                ->where('branch_id', $attributes['branch_id'])
                ->where('catalog_item_variant_id', $item->catalog_item_variant_id)
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                BranchInventory::query()->create([
                    'branch_id' => $attributes['branch_id'],
                    'catalog_item_variant_id' => $item->catalog_item_variant_id,
                    'quantity' => $attributes['quantity'],
                ]);
            } else {
                $inventory->update([
                    'quantity' => $inventory->quantity + $attributes['quantity'],
                ]);
            }

            BranchVariantPrice::query()->updateOrCreate(
                [
                    'branch_id' => $attributes['branch_id'],
                    'catalog_item_variant_id' => $item->catalog_item_variant_id,
                ],
                ['price' => $attributes['selling_price']],
            );

            $item->variant?->update([
                'price' => $attributes['selling_price'],
            ]);

            return $assignment;
        });
    }
}
