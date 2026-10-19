<?php

namespace App\Models;

use Database\Factories\StockConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'from_catalog_item_variant_id',
    'to_catalog_item_variant_id',
    'from_quantity',
    'to_quantity',
    'unit_cost',
    'notes',
])]
class StockConversion extends Model
{
    /** @use HasFactory<StockConversionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_quantity' => 'integer',
            'to_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fromVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'from_catalog_item_variant_id');
    }

    public function toVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'to_catalog_item_variant_id');
    }
}
