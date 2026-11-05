<?php

namespace Database\Factories;

use App\Models\SmsCreditBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsCreditBalance>
 */
class SmsCreditBalanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'balance' => fake()->numberBetween(0, 5000),
            'checked_at' => now(),
        ];
    }
}
