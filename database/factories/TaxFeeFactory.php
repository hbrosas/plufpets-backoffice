<?php

namespace Database\Factories;

use App\Enums\TaxFeeType;
use App\Models\TaxFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxFee>
 */
class TaxFeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => TaxFeeType::Percent,
            'rate' => fake()->randomFloat(2, 1, 20),
            'is_active' => true,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TaxFeeType::Fixed,
            'rate' => fake()->randomFloat(2, 10, 200),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
