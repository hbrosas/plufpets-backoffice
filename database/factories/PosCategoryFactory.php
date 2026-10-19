<?php

namespace Database\Factories;

use App\Models\PosCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosCategory>
 */
class PosCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
