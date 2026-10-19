<?php

namespace Database\Factories;

use App\Models\CatalogItemVariant;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSaleItem>
 */
class PosSaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->randomFloat(2, 20, 200);

        return [
            'pos_sale_id' => PosSale::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'product_name' => fake()->words(3, true),
            'variant_name' => 'Default',
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ];
    }
}
