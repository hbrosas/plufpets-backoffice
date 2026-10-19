<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('custom.????'),
            'name' => fake()->words(2, true),
            'group' => 'Custom',
            'sort_order' => 0,
        ];
    }
}
