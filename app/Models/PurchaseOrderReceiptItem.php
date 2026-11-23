<?php

namespace App\Models;

use App\Models\Concerns\HidesTestTransactions;
use Database\Factories\PurchaseOrderReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_receipt_id', 'purchase_order_item_id', 'quantity'])]
class PurchaseOrderReceiptItem extends Model
{
    /** @use HasFactory<PurchaseOrderReceiptItemFactory> */
    use HasFactory, HidesTestTransactions;

    /**
     * @return list<string>
     */
    public function testBranchColumns(): array
    {
        return [];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function hideTestRelations(Builder $query): void
    {
        $query->whereHas('receipt');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
