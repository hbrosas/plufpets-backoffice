<?php

namespace App\Models;

use App\Enums\VoucherType;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'type', 'amount', 'is_active'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === VoucherType::Percent
            ? round($subtotal * ((float) $this->amount) / 100, 2)
            : (float) $this->amount;

        return min(max($discount, 0), $subtotal);
    }
}
