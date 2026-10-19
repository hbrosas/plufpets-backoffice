<?php

namespace App\Models;

use Database\Factories\PosSaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pos_sale_id',
    'catalog_item_variant_id',
    'product_name',
    'variant_name',
    'sku',
    'notes',
    'modifiers',
    'quantity',
    'unit_price',
    'line_total',
])]
class PosSaleItem extends Model
{
    /** @use HasFactory<PosSaleItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'modifiers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PosSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /**
     * @return BelongsTo<CatalogItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogItemVariant::class, 'catalog_item_variant_id');
    }
}
