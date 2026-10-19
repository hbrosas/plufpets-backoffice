<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'registered_business_name', 'tin', 'code', 'address', 'phone', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
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
            'is_active' => 'boolean',
        ];
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function variantPrices(): HasMany
    {
        return $this->hasMany(BranchVariantPrice::class);
    }

    public function posShifts(): HasMany
    {
        return $this->hasMany(PosShift::class);
    }

    /**
     * @return HasMany<Printer, $this>
     */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    /**
     * @return HasMany<PosOpenOrder, $this>
     */
    public function posOpenOrders(): HasMany
    {
        return $this->hasMany(PosOpenOrder::class);
    }
}
