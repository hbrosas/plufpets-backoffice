<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'instructions' => fake()->optional()->sentence(),
            'is_cash' => false,
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Cash',
            'is_cash' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
