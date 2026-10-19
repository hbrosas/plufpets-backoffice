<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function withPermissions(array $keys): static
    {
        return $this->afterCreating(function (Role $role) use ($keys): void {
            $permissionIds = Permission::query()->whereIn('key', $keys)->pluck('id');

            $role->permissions()->sync($permissionIds);
        });
    }
}
