<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePurchaseOrder
{
    /**
     * @param  array{supplier_id: int, branch_id: int, notes?: string|null, items: list<array{catalog_item_variant_id: int, quantity: int, unit_cost: mixed}>}  $attributes
     */
    public function handle(array $attributes, ?PurchaseOrder $purchaseOrder = null): PurchaseOrder
    {
        return DB::transaction(function () use ($attributes, $purchaseOrder): PurchaseOrder {
            $order = $purchaseOrder ?? new PurchaseOrder;

            if ($order->exists && ! $order->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Confirmed purchase orders cannot be edited.',
                ]);
            }

            $order->fill([
                'supplier_id' => $attributes['supplier_id'],
                'branch_id' => $attributes['branch_id'],
                'notes' => $attributes['notes'] ?? null,
                'ordered_at' => $order->ordered_at ?? now(),
                'status' => $order->status ?? PurchaseOrderStatus::Draft,
            ]);

            if (! $order->exists) {
                $order->number = 'PO-TEMP';
                $order->save();
                $order->update([
                    'number' => 'PO-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
                ]);
            } else {
                $order->save();
            }

            $order->items()->delete();

            foreach ($attributes['items'] as $item) {
                $order->items()->create([
                    'catalog_item_variant_id' => $item['catalog_item_variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'received_quantity' => 0,
                ]);
            }

            return $order->refresh()->load(['supplier', 'items.variant.catalogItem']);
        });
    }
}
