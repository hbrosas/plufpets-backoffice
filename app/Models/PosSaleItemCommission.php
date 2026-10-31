<?php

namespace App\Models;

use App\Enums\CommissionRole;
use Database\Factories\PosSaleItemCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pos_sale_item_id',
    'employee_id',
    'employee_number',
    'employee_name',
    'role',
    'rate',
    'pool_size',
    'commissionable_amount',
    'amount',
])]
class PosSaleItemCommission extends Model
{
    /** @use HasFactory<PosSaleItemCommissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CommissionRole::class,
            'rate' => 'decimal:2',
            'pool_size' => 'integer',
            'commissionable_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosSaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
