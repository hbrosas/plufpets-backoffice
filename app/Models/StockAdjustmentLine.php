<?php

namespace App\Models;

use App\Models\Concerns\HidesTestTransactions;
use Database\Factories\StockAdjustmentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_adjustment_id',
    'catalog_item_variant_id',
    'previous_quantity',
    'quantity',
    'record_as_sale',
])]
class StockAdjustmentLine extends Model
{
    /** @use HasFactory<StockAdjustmentLineFactory> */
    use HasFactory, HidesTestTransactions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_quantity' => 'integer',
            'quantity' => 'integer',
            'record_as_sale' => 'boolean',
        ];
    }

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
        $query->whereHas('adjustment');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'catalog_item_variant_id');
    }
}
