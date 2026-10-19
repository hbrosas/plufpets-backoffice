<?php

namespace App\Models;

use App\Enums\PosCashMovementType;
use App\Enums\PosDenominationCountType;
use App\Enums\PosShiftStatus;
use App\Support\SalesFilters;
use Database\Factories\PosShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'status',
    'opened_by_employee_number',
    'opened_by_name',
    'opened_at',
    'closed_by_employee_number',
    'closed_by_name',
    'closed_at',
    'starting_cash',
    'counted_cash',
    'expected_cash',
    'variance',
    'remit_amount',
])]
class PosShift extends Model
{
    /** @use HasFactory<PosShiftFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PosShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'starting_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'remit_amount' => 'decimal:2',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null && $this->status === PosShiftStatus::Open;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNull('closed_at')->where('status', PosShiftStatus::Open);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function inPeriod(Builder $query, SalesFilters $filters): Builder
    {
        return $query
            ->whereBetween('opened_at', [$filters->from, $filters->to])
            ->when($filters->branchId, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<PosShiftDenomination, $this>
     */
    public function denominations(): HasMany
    {
        return $this->hasMany(PosShiftDenomination::class);
    }

    /**
     * @return HasMany<PosShiftDenomination, $this>
     */
    public function openingDenominations(): HasMany
    {
        return $this->hasMany(PosShiftDenomination::class)
            ->where('count_type', PosDenominationCountType::Opening);
    }

    /**
     * @return HasMany<PosShiftDenomination, $this>
     */
    public function closingDenominations(): HasMany
    {
        return $this->hasMany(PosShiftDenomination::class)
            ->where('count_type', PosDenominationCountType::Closing);
    }

    /**
     * @return HasMany<PosCashMovement, $this>
     */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class)->orderBy('recorded_at')->orderBy('id');
    }

    /**
     * @return HasMany<PosSale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class)->orderByDesc('sold_at')->orderByDesc('id');
    }

    public function cashInTotal(): string
    {
        return number_format((float) $this->cashMovements()
            ->where('type', PosCashMovementType::CashIn)
            ->sum('amount'), 2, '.', '');
    }

    public function cashOutTotal(): string
    {
        return number_format((float) $this->cashMovements()
            ->where('type', PosCashMovementType::CashOut)
            ->sum('amount'), 2, '.', '');
    }

    public function cashSalesTotal(): string
    {
        return number_format((float) PosSalePayment::query()
            ->where('is_cash', true)
            ->whereHas('sale', fn ($query) => $query->where('pos_shift_id', $this->id)->sold())
            ->sum('amount'), 2, '.', '');
    }

    public function expectedDrawerCash(): string
    {
        $expected = (float) $this->starting_cash
            + (float) $this->cashSalesTotal()
            + (float) $this->cashInTotal()
            - (float) $this->cashOutTotal();

        return number_format($expected, 2, '.', '');
    }
}
