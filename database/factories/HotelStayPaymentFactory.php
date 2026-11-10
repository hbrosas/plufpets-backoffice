<?php

namespace Database\Factories;

use App\Models\HotelStay;
use App\Models\HotelStayPayment;
use App\Models\PaymentMethod;
use App\Models\PosShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelStayPayment>
 */
class HotelStayPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_stay_id' => HotelStay::factory(),
            'pos_shift_id' => PosShift::factory(),
            'payment_method_id' => PaymentMethod::factory()->cash(),
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'amount' => '500.00',
            'amount_tendered' => '500.00',
            'change_due' => '0.00',
            'recorded_by_employee_number' => '10001',
            'recorded_by_name' => 'Sample Employee',
            'recorded_at' => now(),
        ];
    }
}
