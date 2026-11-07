<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use App\Support\PayrollCalendar;
use App\Support\PayrollRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $employees = $this->input('employees', []);

        if (! is_array($employees)) {
            return;
        }

        $holidays = $this->holidaysInPeriod();
        $prepared = [];

        foreach ($employees as $employee) {
            if (! is_array($employee)) {
                $prepared[] = $employee;

                continue;
            }

            if ($holidays !== null && is_array($employee['worked_dates'] ?? null)) {
                $attendance = PayrollCalendar::attendanceFromDates(
                    (string) $this->input('period_start'),
                    (string) $this->input('period_end'),
                    $employee['worked_dates'],
                    $holidays,
                );
                $employee['days_worked'] = $attendance['days_worked'];
                $employee['worked_holiday_ids'] = $attendance['worked_holiday_ids'];
                $employee['worked_dates'] = $attendance['worked_dates'];
            }

            $row = PayrollRules::prepare([
                ...$employee,
                'period_start' => $this->input('period_start'),
                'period_end' => $this->input('period_end'),
                'deduct_government' => $this->exists('deduct_government') ? $this->boolean('deduct_government') : true,
            ]);

            $preparedRow = [
                'id' => $employee['id'] ?? null,
                'days_worked' => $row['days_worked'] ?? null,
                'late_hours' => $row['late_hours'] ?? null,
                'overtime_hours' => $row['overtime_hours'] ?? null,
                'overnight_nights' => $row['overnight_nights'] ?? '0',
                'worked_holiday_ids' => $row['worked_holiday_ids'] ?? [],
                'adjustments' => $row['adjustments'] ?? [],
            ];

            if (is_array($employee['worked_dates'] ?? null)) {
                $preparedRow['worked_dates'] = array_values($employee['worked_dates']);
            }

            $prepared[] = $preparedRow;
        }

        $this->merge([
            'deduct_government' => $this->exists('deduct_government') ? $this->boolean('deduct_government') : true,
            'employees' => $prepared,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fields = PayrollRules::for();
        unset($fields['period_start'], $fields['period_end'], $fields['deduct_government']);

        $rules = [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'deduct_government' => ['required', 'boolean'],
            'employees' => ['required', 'array', 'min:1', 'max:100'],
            'employees.*.id' => ['required', 'integer', 'distinct', 'exists:employees,id'],
        ];

        foreach ($fields as $key => $rule) {
            $rules['employees.*.'.$key] = $rule;
        }

        // Distinct on this nested key compares every employee together, so one
        // marked holiday would block the same holiday for everyone else.
        $rules['employees.*.worked_holiday_ids.*'] = ['integer', 'exists:holidays,id'];
        $rules['employees.*.worked_dates'] = ['sometimes', 'array', 'max:31'];
        $rules['employees.*.worked_dates.*'] = ['date', 'after_or_equal:period_start', 'before_or_equal:period_end'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'period_start.required' => 'Enter the payroll start date.',
            'period_start.date' => 'Enter the payroll start date.',
            'period_end.required' => 'Enter the payroll end date.',
            'period_end.date' => 'Enter the payroll end date.',
            'period_end.after_or_equal' => 'The end date must be on or after the start date.',
            'deduct_government.required' => 'Choose whether to deduct government benefits.',
            'deduct_government.boolean' => 'Choose whether to deduct government benefits.',
            'employees.required' => 'Choose at least one employee.',
            'employees.min' => 'Choose at least one employee.',
            'employees.*.id.required' => 'Choose an employee on the list.',
            'employees.*.id.exists' => 'Choose an employee on the list.',
            'employees.*.id.distinct' => 'Each employee can only be selected once.',
            'employees.*.worked_dates.max' => 'Choose at most 31 days.',
            'employees.*.worked_dates.*.date' => 'Choose a day inside this payroll period.',
            'employees.*.worked_dates.*.after_or_equal' => 'Choose a day inside this payroll period.',
            'employees.*.worked_dates.*.before_or_equal' => 'Choose a day inside this payroll period.',
        ];

        foreach (PayrollRules::messages() as $key => $message) {
            if (str_starts_with($key, 'period_')) {
                continue;
            }

            $messages['employees.*.'.$key] = $message;
        }

        return $messages;
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                PayrollRules::afterRun($validator);
            },
        ];
    }

    /**
     * @return Collection<int, Holiday>|null
     */
    private function holidaysInPeriod(): ?Collection
    {
        $start = $this->input('period_start');
        $end = $this->input('period_end');

        if (! is_string($start) || ! is_string($end)) {
            return null;
        }

        try {
            $startDate = Carbon::parse($start)->toDateString();
            $endDate = Carbon::parse($end)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return Holiday::query()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }
}
