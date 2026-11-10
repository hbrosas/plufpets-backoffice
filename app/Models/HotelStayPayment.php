<?php

namespace App\Models;

use Database\Factories\HotelStayPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_stay_id',
    'pos_shift_id',
    'pos_cash_movement_id',
    'payment_method_id',
    'payment_method_name',
    'is_cash',
    'amount',
    'amount_tendered',
    'change_due',
    'reference_number',
    'recorded_by_employee_number',
    'recorded_by_name',
    'recorded_at',
])]
class HotelStayPayment extends Model
{
    /** @use HasFactory<HotelStayPaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HotelStay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(HotelStay::class, 'hotel_stay_id');
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    /**
     * @return BelongsTo<PosCashMovement, $this>
     */
    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(PosCashMovement::class, 'pos_cash_movement_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
