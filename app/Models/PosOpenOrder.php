<?php

namespace App\Models;

use App\Enums\InventoryHold;
use Database\Factories\PosOpenOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'number',
    'name',
    'items',
    'subtotal',
    'saved_by_employee_number',
    'saved_by_name',
    'phone',
    'status',
    'delivery_schedule_id',
    'preferred_on',
    'inventory_hold',
    'discount',
    'total',
    'checkout',
    'pos_sale_id',
])]
class PosOpenOrder extends Model
{
    /** @use HasFactory<PosOpenOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'inventory_hold' => InventoryHold::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'checkout' => 'array',
            'preferred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PosSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /**
     * @return BelongsTo<DeliverySchedule, $this>
     */
    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    public function hasPreorder(): bool
    {
        foreach ($this->items ?? [] as $item) {
            if (is_array($item) && is_string($item['notes'] ?? null) && strcasecmp($item['notes'], 'Preorder') === 0) {
                return true;
            }
        }

        return false;
    }
}
