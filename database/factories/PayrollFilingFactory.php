<?php

namespace Database\Factories;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Models\Employee;
use App\Models\PayrollFiling;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollFiling>
 */
class PayrollFilingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = now()->toDateString();

        return [
            'employee_id' => Employee::factory(),
            'type' => PayrollFilingType::Leave,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => $day,
            'ends_on' => $day,
            'hours' => null,
            'nights' => null,
            'reason' => 'Family day',
        ];
    }

    public function overtime(): static
    {
        return $this->state(fn (): array => [
            'type' => PayrollFilingType::Overtime,
            'ends_on' => null,
            'hours' => '2.50',
            'nights' => null,
            'reason' => 'Late grooming',
        ]);
    }

    public function overnight(): static
    {
        return $this->state(fn (): array => [
            'type' => PayrollFilingType::Overnight,
            'ends_on' => null,
            'hours' => null,
            'nights' => 1,
            'reason' => 'Boarding',
        ]);
    }

    public function cashAdvance(): static
    {
        return $this->state(fn (): array => [
            'type' => PayrollFilingType::CashAdvance,
            'ends_on' => null,
            'hours' => null,
            'nights' => null,
            'amount' => '500.00',
            'reason' => 'Emergency',
        ]);
    }
}
