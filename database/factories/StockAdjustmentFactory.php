<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'scope' => 'all',
            'brand_id' => null,
            'category_id' => null,
            'record_as_sale' => false,
            'pos_sale_id' => null,
            'recorded_by_employee_number' => '10001',
            'recorded_by_name' => 'Sample Employee',
        ];
    }
}
