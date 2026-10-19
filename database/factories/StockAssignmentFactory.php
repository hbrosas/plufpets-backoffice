<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PurchaseOrderItem;
use App\Models\StockAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAssignment>
 */
class StockAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'branch_id' => Branch::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'selling_price' => fake()->randomFloat(2, 50, 800),
        ];
    }
}
