<?php

namespace App\Models;

use Database\Factories\BranchModifierOptionPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'catalog_item_modifier_option_price_id', 'price'])]
class BranchModifierOptionPrice extends Model
{
    /** @use HasFactory<BranchModifierOptionPriceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function optionPrice(): BelongsTo
    {
        return $this->belongsTo(CatalogItemModifierOptionPrice::class, 'catalog_item_modifier_option_price_id');
    }
}
