<?php

namespace Database\Factories;

use App\Models\CatalogItemVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentLine>
 */
class StockAdjustmentLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'previous_quantity' => 5,
            'quantity' => 3,
            'record_as_sale' => false,
        ];
    }
}
