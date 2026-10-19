<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CatalogItemVariant;
use App\Models\PosOpenOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosOpenOrder>
 */
class PosOpenOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $variant = CatalogItemVariant::factory()->create();
        $price = (float) $variant->price;
        $quantity = fake()->numberBetween(1, 3);

        return [
            'branch_id' => Branch::factory(),
            'number' => strtoupper(fake()->unique()->bothify('OPEN-######')),
            'name' => fake()->words(2, true),
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'product_name' => $variant->catalogItem?->name ?? $variant->name,
                'variant_name' => $variant->name,
                'price' => number_format($price, 2, '.', ''),
                'quantity' => $quantity,
                'notes' => null,
                'modifiers' => [],
                'is_product' => true,
            ]],
            'subtotal' => number_format($price * $quantity, 2, '.', ''),
            'saved_by_employee_number' => '10001',
            'saved_by_name' => 'Sample Employee',
        ];
    }
}
