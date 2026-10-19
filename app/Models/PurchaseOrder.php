<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'supplier_id', 'branch_id', 'status', 'ordered_at', 'confirmed_at', 'notes'])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PurchaseOrderStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class)->latest('received_at')->latest('id');
    }

    public function orderedQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function totalAmount(): float
    {
        return (float) $this->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->unit_cost * $item->quantity);
    }

    public function receivedQuantity(): int
    {
        return (int) $this->items->sum('received_quantity');
    }

    public function deliveryStatus(): DeliveryStatus
    {
        $received = $this->receivedQuantity();
        $ordered = $this->orderedQuantity();

        if ($received === 0) {
            return DeliveryStatus::ForDelivery;
        }

        if ($received < $ordered) {
            return DeliveryStatus::PartiallyDelivered;
        }

        return DeliveryStatus::Completed;
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receiptHistory(): array
    {
        return $this->receipts->map(fn (PurchaseOrderReceipt $receipt): array => [
            'id' => $receipt->id,
            'delivery_receipt_number' => $receipt->delivery_receipt_number,
            'sales_invoice_number' => $receipt->sales_invoice_number,
            'received_by' => $receipt->received_by_name,
            'received_by_employee_number' => $receipt->received_by_employee_number,
            'received_at' => $receipt->received_at?->toDayDateTimeString(),
            'items' => $receipt->items->map(fn (PurchaseOrderReceiptItem $item): array => [
                'id' => $item->id,
                'product' => $item->purchaseOrderItem?->variant?->catalogItem?->name,
                'variant' => $item->purchaseOrderItem?->variant?->name,
                'quantity' => $item->quantity,
            ])->all(),
        ])->all();
    }

    #[Scope]
    protected function confirmed(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Confirmed);
    }
}
