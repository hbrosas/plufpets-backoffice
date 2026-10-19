<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CatalogItemVariant;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_branch_id' => Branch::factory(),
            'to_branch_id' => Branch::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'quantity' => fake()->numberBetween(1, 8),
            'notes' => null,
        ];
    }
}
