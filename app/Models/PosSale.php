<?php

namespace App\Models;

use App\Support\SalesFilters;
use Database\Factories\PosSaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pos_shift_id',
    'branch_id',
    'number',
    'payment_method_id',
    'payment_method_name',
    'is_cash',
    'delivery_method_id',
    'delivery_method_name',
    'delivery_fee',
    'subtotal',
    'tax_total',
    'total',
    'amount_tendered',
    'change_due',
    'sold_by_employee_number',
    'sold_by_name',
    'sold_at',
    'refunded_at',
    'refunded_by_employee_number',
    'refunded_by_name',
    'refund_reason',
])]
class PosSale extends Model
{
    /** @use HasFactory<PosSaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'delivery_fee' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'sold_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<PosSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    /**
     * @return HasMany<PosSaleTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(PosSaleTax::class);
    }

    /**
     * @return HasMany<PosSalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PosSalePayment::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function inPeriod(Builder $query, SalesFilters $filters): Builder
    {
        return $query
            ->whereBetween('sold_at', [$filters->from, $filters->to])
            ->when($filters->branchId, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function sold(Builder $query): Builder
    {
        return $query->whereNull('refunded_at');
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }
}
