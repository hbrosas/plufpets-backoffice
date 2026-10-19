<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-'.fake()->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'ordered_at' => now(),
            'confirmed_at' => null,
            'notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
