<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSale>
 */
class PosSaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 50, 500);

        return [
            'pos_shift_id' => PosShift::factory(),
            'branch_id' => fn (array $attributes) => PosShift::query()->find($attributes['pos_shift_id'])?->branch_id ?? Branch::factory(),
            'number' => strtoupper(fake()->unique()->bothify('POS-######')),
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'delivery_fee' => 0,
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
            'amount_tendered' => $total,
            'change_due' => 0,
            'sold_by_employee_number' => '10001',
            'sold_by_name' => 'Sample Employee',
            'sold_at' => now(),
        ];
    }
}
