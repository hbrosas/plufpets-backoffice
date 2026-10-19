<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItemVariant>
 */
class CatalogItemVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_item_id' => CatalogItem::factory(),
            'name' => fake()->randomElement(['Default', 'Small', 'Medium', 'Large']),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'barcode' => fake()->unique()->ean13(),
            'price' => fake()->randomFloat(2, 5, 200),
            'is_active' => true,
        ];
    }
}
