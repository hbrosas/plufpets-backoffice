<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CatalogItemVariant;
use App\Models\StockConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockConversion>
 */
class StockConversionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'from_catalog_item_variant_id' => CatalogItemVariant::factory(),
            'to_catalog_item_variant_id' => CatalogItemVariant::factory(),
            'from_quantity' => 1,
            'to_quantity' => fake()->numberBetween(2, 12),
            'unit_cost' => fake()->randomFloat(2, 5, 80),
            'notes' => null,
        ];
    }
}
