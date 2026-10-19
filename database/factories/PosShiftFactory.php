<?php

namespace Database\Factories;

use App\Enums\PosShiftStatus;
use App\Models\Branch;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
class PosShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'status' => PosShiftStatus::Open,
            'opened_by_employee_number' => '10001',
            'opened_by_name' => 'Sample Employee',
            'opened_at' => now(),
            'starting_cash' => 0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PosShiftStatus::Closed,
            'closed_by_employee_number' => '10001',
            'closed_by_name' => 'Sample Employee',
            'closed_at' => now(),
            'counted_cash' => $attributes['starting_cash'] ?? 0,
            'expected_cash' => $attributes['starting_cash'] ?? 0,
            'variance' => 0,
            'remit_amount' => $attributes['starting_cash'] ?? 0,
        ]);
    }
}
