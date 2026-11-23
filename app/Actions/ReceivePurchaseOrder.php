<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    /**
     * @param  list<array{id: int, quantity: int}>  $items
     * @param  array{delivery_receipt_number: string|null, sales_invoice_number: string|null, notes: string|null, received_by_employee_number: string, received_by_name: string}  $receipt
     */
    public function handle(PurchaseOrder $purchaseOrder, array $items, array $receipt): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $items, $receipt): PurchaseOrder {
            $order = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === PurchaseOrderStatus::Closed) {
                throw ValidationException::withMessages([
                    'status' => 'This purchase order is closed.',
                ]);
            }

            if ($order->status !== PurchaseOrderStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'status' => 'Confirm this purchase order before recording a delivery.',
                ]);
            }

            /** @var array<int, int> $quantities */
            $quantities = [];

            foreach ($items as $row) {
                $quantities[(int) $row['id']] = (int) $row['quantity'];
            }

            $locked = PurchaseOrderItem::query()
                ->where('purchase_order_id', $order->id)
                ->whereIn('id', array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($locked->count() !== count($quantities)) {
                throw ValidationException::withMessages([
                    'items' => 'One of the products is not on this purchase order.',
                ]);
            }

            foreach ($quantities as $id => $quantity) {
                $item = $locked->get($id);

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the products is not on this purchase order.',
                    ]);
                }

                if ($quantity > $item->remainingToReceive()) {
                    $item->loadMissing('variant.catalogItem');

                    throw ValidationException::withMessages([
                        'items' => 'You cannot receive more than the remaining quantity for '.$item->variant?->catalogItem?->name.'.',
                    ]);
                }
            }

            $recorded = PurchaseOrderReceipt::query()->create([
                'purchase_order_id' => $order->id,
                'delivery_receipt_number' => $receipt['delivery_receipt_number'],
                'sales_invoice_number' => $receipt['sales_invoice_number'],
                'notes' => $receipt['notes'] ?? null,
                'received_by_employee_number' => $receipt['received_by_employee_number'],
                'received_by_name' => $receipt['received_by_name'],
                'received_at' => now(),
            ]);

            foreach (array_chunk($quantities, 200, true) as $chunk) {
                $this->addReceivedQuantities($order, $chunk);
                $this->insertReceiptLines($recorded, $chunk);
            }

            return $order;
        });
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function addReceivedQuantities(PurchaseOrder $order, array $quantities): void
    {
        $cases = [];

        foreach ($quantities as $id => $quantity) {
            $cases[] = 'when '.(int) $id.' then received_quantity + '.(int) $quantity;
        }

        PurchaseOrderItem::query()
            ->where('purchase_order_id', $order->id)
            ->whereIn('id', array_keys($quantities))
            ->update([
                'received_quantity' => DB::raw('case id '.implode(' ', $cases).' else received_quantity end'),
            ]);
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function insertReceiptLines(PurchaseOrderReceipt $receipt, array $quantities): void
    {
        $timestamp = now();

        PurchaseOrderReceiptItem::query()->insert(array_map(
            fn (int $id): array => [
                'purchase_order_receipt_id' => $receipt->id,
                'purchase_order_item_id' => $id,
                'quantity' => $quantities[$id],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            array_keys($quantities),
        ));
    }
}
