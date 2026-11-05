<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class ClosePurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder, string $reason): PurchaseOrder
    {
        if ($purchaseOrder->status === PurchaseOrderStatus::Closed) {
            throw ValidationException::withMessages([
                'reason' => 'This purchase order is already closed.',
            ]);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Closed,
            'closed_reason' => $reason,
            'closed_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }
}
