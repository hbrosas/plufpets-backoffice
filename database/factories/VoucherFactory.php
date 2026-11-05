<?php

namespace Database\Factories;

use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'type' => VoucherType::Fixed,
            'amount' => fake()->randomFloat(2, 10, 100),
            'is_active' => true,
        ];
    }

    public function percent(float $amount = 10): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => VoucherType::Percent,
            'amount' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
