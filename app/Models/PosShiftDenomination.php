<?php

namespace App\Models;

use App\Enums\PosDenominationCountType;
use Database\Factories\PosShiftDenominationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pos_shift_id', 'count_type', 'denomination', 'quantity'])]
class PosShiftDenomination extends Model
{
    /** @use HasFactory<PosShiftDenominationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count_type' => PosDenominationCountType::class,
            'denomination' => 'decimal:2',
            'quantity' => 'integer',
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
