<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchModifierOptionPrice;
use App\Models\CatalogItemModifierOptionPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchModifierOptionPrice>
 */
class BranchModifierOptionPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'catalog_item_modifier_option_price_id' => CatalogItemModifierOptionPrice::factory(),
            'price' => fake()->randomFloat(2, 0, 50),
        ];
    }
}
