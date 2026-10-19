<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'date_of_birth' => '1995-04-12',
            'start_date' => '2024-06-01',
            'employee_number' => fake()->unique()->numberBetween(20000, 89999),
            'pin' => '1234',
            'role_id' => Role::factory(),
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
