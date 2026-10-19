<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchVariantPrice;
use App\Models\CatalogItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchVariantPrice>
 */
class BranchVariantPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'price' => fake()->randomFloat(2, 5, 200),
        ];
    }
}
