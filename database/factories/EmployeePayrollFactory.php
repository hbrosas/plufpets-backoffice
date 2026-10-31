<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeePayroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePayroll>
 */
class EmployeePayrollFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_name' => 'Mina Cruz',
            'employee_number' => '31002',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'net' => '1000.00',
            'snapshot' => [
                'employee_name' => 'Mina Cruz',
                'employee_number' => '31002',
                'job_title' => 'Groomer',
                'payroll' => [
                    'period_start' => '2026-10-01',
                    'period_end' => '2026-10-15',
                    'earnings' => [],
                    'deductions' => [],
                    'employer_shares' => [],
                    'gross' => '1000.00',
                    'total_deductions' => '0.00',
                    'net' => '1000.00',
                ],
            ],
        ];
    }
}
