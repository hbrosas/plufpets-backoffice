<?php

namespace App\Models;

use App\Enums\PosCashMovementType;
use App\Models\Concerns\HidesTestTransactions;
use Database\Factories\PosCashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pos_shift_id',
    'type',
    'amount',
    'reason',
    'employee_number',
    'employee_name',
    'recorded_at',
])]
class PosCashMovement extends Model
{
    /** @use HasFactory<PosCashMovementFactory> */
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
        $query->whereHas('shift');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PosCashMovementType::class,
            'amount' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }
}
