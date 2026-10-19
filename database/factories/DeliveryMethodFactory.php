<?php

namespace Database\Factories;

use App\Models\DeliveryMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryMethod>
 */
class DeliveryMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'fee' => fake()->randomFloat(2, 0, 250),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function pricedInCart(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fee' => null,
        ]);
    }
}
