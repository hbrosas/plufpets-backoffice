<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    /**
     * @param  list<array{id: int, quantity: int}>  $items
     * @param  array{delivery_receipt_number: string|null, sales_invoice_number: string|null, received_by_employee_number: string, received_by_name: string}  $receipt
     */
    public function handle(PurchaseOrder $purchaseOrder, array $items, array $receipt): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $items, $receipt): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== PurchaseOrderStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'status' => 'Confirm this purchase order before recording a delivery.',
                ]);
            }

            $recorded = PurchaseOrderReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'delivery_receipt_number' => $receipt['delivery_receipt_number'],
                'sales_invoice_number' => $receipt['sales_invoice_number'],
                'received_by_employee_number' => $receipt['received_by_employee_number'],
                'received_by_name' => $receipt['received_by_name'],
                'received_at' => now(),
            ]);

            foreach ($items as $row) {
                $item = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $order->id)
                    ->whereKey($row['id'])
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the products is not on this purchase order.',
                    ]);
                }

                $remaining = $item->remainingToReceive();

                if ($row['quantity'] > $remaining) {
                    $item->loadMissing('variant.catalogItem');

                    throw ValidationException::withMessages([
                        'items' => 'You cannot receive more than the remaining quantity for '.$item->variant?->catalogItem?->name.'.',
                    ]);
                }

                $item->update([
                    'received_quantity' => $item->received_quantity + $row['quantity'],
                ]);

                $recorded->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $order->refresh()->load(['supplier', 'items.variant.catalogItem', 'receipts.items.purchaseOrderItem.variant.catalogItem']);
        });
    }
}
