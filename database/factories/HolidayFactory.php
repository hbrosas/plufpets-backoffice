<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->date(),
            'name' => fake()->words(3, true),
            'type' => HolidayType::Regular,
        ];
    }

    public function special(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => HolidayType::Special,
        ]);
    }
}
