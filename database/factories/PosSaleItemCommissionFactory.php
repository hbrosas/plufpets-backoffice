<?php

namespace Database\Factories;

use App\Enums\CommissionRole;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSaleItemCommission>
 */
class PosSaleItemCommissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_item_id' => PosSaleItem::factory(),
            'employee_number' => '10001',
            'employee_name' => 'Sample Employee',
            'role' => CommissionRole::Assigned,
            'rate' => 10,
            'pool_size' => 1,
            'commissionable_amount' => 1000,
            'amount' => 100,
        ];
    }
}
