<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\Modifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItemModifierOptionPrice>
 */
class CatalogItemModifierOptionPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_item_id' => CatalogItem::factory(),
            'modifier_id' => Modifier::factory(),
            'option' => fake()->word(),
            'price' => fake()->randomFloat(2, 0, 50),
        ];
    }
}
