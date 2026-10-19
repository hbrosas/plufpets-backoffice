<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class ConfirmPurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! $purchaseOrder->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be confirmed.',
            ]);
        }

        $purchaseOrder->loadMissing('items');

        if ($purchaseOrder->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product before confirming.',
            ]);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }
}
