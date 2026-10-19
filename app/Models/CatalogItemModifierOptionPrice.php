<?php

namespace App\Models;

use Database\Factories\CatalogItemModifierOptionPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['catalog_item_id', 'modifier_id', 'option', 'price'])]
class CatalogItemModifierOptionPrice extends Model
{
    /** @use HasFactory<CatalogItemModifierOptionPriceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(BranchModifierOptionPrice::class);
    }
}
