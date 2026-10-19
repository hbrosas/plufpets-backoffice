<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_order_id', 'catalog_item_variant_id', 'quantity', 'unit_cost', 'received_quantity'])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'received_quantity' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'catalog_item_variant_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StockAssignment::class);
    }

    public function remainingToReceive(): int
    {
        return max($this->quantity - $this->received_quantity, 0);
    }

    public function assignedQuantity(): int
    {
        return (int) $this->assignments->sum('quantity');
    }

    public function remainingToAssign(): int
    {
        return max($this->received_quantity - $this->assignedQuantity(), 0);
    }

    public function lineTotal(): string
    {
        return number_format(((float) $this->unit_cost) * $this->quantity, 2, '.', '');
    }
}
