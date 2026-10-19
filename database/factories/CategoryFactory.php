<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\PosCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'pos_category_id' => PosCategory::factory(),
        ];
    }
}
