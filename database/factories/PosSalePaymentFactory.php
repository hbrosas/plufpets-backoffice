<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\PosSalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSalePayment>
 */
class PosSalePaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 500);

        return [
            'pos_sale_id' => PosSale::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'amount' => $amount,
            'amount_tendered' => $amount,
            'change_due' => 0,
            'reference_number' => null,
        ];
    }
}
