<?php

namespace Database\Factories;

use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'catalog_item_variant_id' => CatalogItemVariant::factory(),
            'quantity' => fake()->numberBetween(5, 40),
            'unit_cost' => fake()->randomFloat(2, 20, 400),
            'received_quantity' => 0,
        ];
    }
}
