<?php

namespace Database\Factories;

use App\Enums\PosCashMovementType;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosCashMovement>
 */
class PosCashMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_shift_id' => PosShift::factory(),
            'type' => PosCashMovementType::CashIn,
            'amount' => 100,
            'reason' => fake()->sentence(4),
            'employee_number' => '10001',
            'employee_name' => 'Sample Employee',
            'recorded_at' => now(),
        ];
    }
}
