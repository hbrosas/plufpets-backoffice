<?php

namespace App\Models;

use App\Support\TestingMode;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'registered_business_name', 'tin', 'code', 'address', 'phone', 'is_active', 'for_test_use'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'for_test_use' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'for_test_use' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function operational(Builder $query): Builder
    {
        if (! TestingMode::includesTestData()) {
            $query->where($query->qualifyColumn('for_test_use'), false);
        }

        return $query;
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
