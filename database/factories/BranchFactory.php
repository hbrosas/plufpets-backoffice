<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Branch',
            'registered_business_name' => 'Pluf Pets Trading',
            'tin' => fake()->numerify('###-###-###-#####'),
            'code' => strtoupper(fake()->unique()->bothify('BR-##')),
            'address' => fake()->address(),
            'phone' => fake()->numerify('09#########'),
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
