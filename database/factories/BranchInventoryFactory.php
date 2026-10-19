<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchInventory>
 */
class BranchInventoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'quantity' => fake()->numberBetween(0, 80),
        ];
    }
}
