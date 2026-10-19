<?php

namespace App\Models;

use Database\Factories\PurchaseOrderReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'delivery_receipt_number',
    'sales_invoice_number',
    'received_by_employee_number',
    'received_by_name',
    'received_at',
])]
class PurchaseOrderReceipt extends Model
{
    /** @use HasFactory<PurchaseOrderReceiptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class)->orderBy('id');
    }
}
