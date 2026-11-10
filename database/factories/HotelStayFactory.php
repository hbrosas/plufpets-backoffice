<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelStay>
 */
class HotelStayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'pos_shift_id' => function (array $attributes): int {
                $branchId = $attributes['branch_id'] instanceof Branch ? $attributes['branch_id']->id : $attributes['branch_id'];
                $openShiftId = PosShift::query()->where('branch_id', $branchId)->open()->value('id');

                if ($openShiftId !== null) {
                    return (int) $openShiftId;
                }

                return PosShift::factory()->create(['branch_id' => $branchId])->id;
            },
            'client_name' => fake()->name(),
            'contact_number' => '09171234567',
            'address' => fake()->streetAddress(),
            'checked_in_at' => now(),
            'checkout_on' => now()->addDay()->toDateString(),
            'hotel_pod_type_id' => HotelPodType::factory(),
            'checked_in_by_employee_number' => '10001',
            'checked_in_by_name' => 'Sample Employee',
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(fn (array $attributes): array => [
            'checked_out_at' => now(),
            'checked_out_by_employee_number' => '10001',
            'checked_out_by_name' => 'Sample Employee',
        ]);
    }
}
