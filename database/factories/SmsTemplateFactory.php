<?php

namespace Database\Factories;

use App\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsTemplate>
 */
class SmsTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'body' => 'Your order is ready for pickup.',
        ];
    }
}
