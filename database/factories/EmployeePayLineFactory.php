<?php

namespace Database\Factories;

use App\Enums\PayLineKind;
use App\Models\EmployeePayLine;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePayLine>
 */
class EmployeePayLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_profile_id' => EmployeeProfile::factory(),
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => 15000,
            'sort' => 0,
        ];
    }
}
