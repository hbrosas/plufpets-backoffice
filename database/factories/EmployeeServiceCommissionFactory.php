<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\Employee;
use App\Models\EmployeeServiceCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeServiceCommission>
 */
class EmployeeServiceCommissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'catalog_item_id' => CatalogItem::factory()->service(),
            'assigned_rate' => 0,
            'assisted_rate' => 0,
        ];
    }
}
