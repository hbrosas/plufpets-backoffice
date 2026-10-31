<?php

namespace App\Support;

use App\Models\Holiday;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayrollRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function prepare(array $input): array
    {
        $ids = $input['worked_holiday_ids'] ?? [];

        if (! is_array($ids)) {
            $ids = ($ids === '' || $ids === null) ? [] : [$ids];
        }

        $input['worked_holiday_ids'] = array_values($ids);
        $input['deduct_government'] = filter_var($input['deduct_government'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $input['overnight_nights'] = ($input['overnight_nights'] ?? '') === '' ? '0' : $input['overnight_nights'];
        $input['adjustments'] = self::adjustments($input['adjustments'] ?? []);

        return $input;
    }

    /**
     * @return list<array{name: string, side: string, amount: mixed}>
     */
    private static function adjustments(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $prepared = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $prepared[] = ['name' => '', 'side' => 'earning', 'amount' => null];

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $amount = $row['amount'] ?? null;
            $amount = ($amount === '' || $amount === null) ? null : $amount;

            if ($name === '' && $amount === null) {
                continue;
            }

            $prepared[] = [
                'name' => $name,
                'side' => (string) ($row['side'] ?? ''),
                'amount' => $amount,
            ];
        }

        return $prepared;
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'days_worked' => ['required', 'numeric', 'min:0', 'max:31', 'decimal:0,2'],
            'late_hours' => ['required', 'numeric', 'min:0', 'max:744', 'decimal:0,2'],
            'overtime_hours' => ['required', 'numeric', 'min:0', 'max:744', 'decimal:0,2'],
            'overnight_nights' => ['required', 'integer', 'min:0', 'max:31'],
            'deduct_government' => ['required', 'boolean'],
            'worked_holiday_ids' => ['present', 'array'],
            'worked_holiday_ids.*' => ['integer', 'distinct', 'exists:holidays,id'],
            'adjustments' => ['present', 'array', 'max:20'],
            'adjustments.*.name' => ['required', 'string', 'max:255'],
            'adjustments.*.side' => ['required', Rule::in(['earning', 'deduction'])],
            'adjustments.*.amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'period_start.required' => 'Enter the payroll start date.',
            'period_start.date' => 'Enter the payroll start date.',
            'period_end.required' => 'Enter the payroll end date.',
            'period_end.date' => 'Enter the payroll end date.',
            'period_end.after_or_equal' => 'The end date must be on or after the start date.',
            'days_worked.required' => 'Enter the number of days worked.',
            'days_worked.numeric' => 'Enter the number of days worked.',
            'days_worked.min' => 'Days worked cannot be negative.',
            'days_worked.max' => 'Days worked cannot be more than 31.',
            'days_worked.decimal' => 'Days worked can have at most 2 decimal places.',
            'late_hours.required' => 'Enter late hours.',
            'late_hours.numeric' => 'Enter late hours.',
            'late_hours.min' => 'Late hours cannot be negative.',
            'late_hours.max' => 'Late hours cannot be more than 744.',
            'late_hours.decimal' => 'Late hours can have at most 2 decimal places.',
            'overtime_hours.required' => 'Enter overtime hours.',
            'overtime_hours.numeric' => 'Enter overtime hours.',
            'overtime_hours.min' => 'Overtime hours cannot be negative.',
            'overtime_hours.max' => 'Overtime hours cannot be more than 744.',
            'overtime_hours.decimal' => 'Overtime hours can have at most 2 decimal places.',
            'overnight_nights.required' => 'Enter overnight nights.',
            'overnight_nights.integer' => 'Enter overnight nights as a whole number.',
            'overnight_nights.min' => 'Overnight nights cannot be negative.',
            'overnight_nights.max' => 'Overnight nights cannot be more than 31.',
            'worked_holiday_ids.*.exists' => 'Choose a holiday on the calendar.',
            'worked_holiday_ids.*.distinct' => 'Each holiday can only be marked once.',
            'adjustments.max' => 'A payroll can have at most 20 one-time items.',
            'adjustments.*.name.required' => 'Enter a name for the one-time item.',
            'adjustments.*.side.required' => 'Choose earning or deduction.',
            'adjustments.*.side.in' => 'Choose earning or deduction.',
            'adjustments.*.amount.required' => 'Enter an amount for the one-time item.',
            'adjustments.*.amount.numeric' => 'Enter an amount for the one-time item.',
            'adjustments.*.amount.min' => 'Enter an amount greater than zero.',
            'adjustments.*.amount.decimal' => 'A one-time amount can have at most 2 decimal places.',
        ];
    }

    public static function after(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['period_start', 'period_end', 'days_worked', 'overnight_nights', 'worked_holiday_ids'])) {
            return;
        }

        $data = $validator->getData();
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->startOfDay();
        $span = (int) $start->diffInDays($end) + 1;

        if ($span > 31) {
            $validator->errors()->add('period_end', 'A payroll period cannot be longer than 31 days.');

            return;
        }

        if ((float) $data['days_worked'] > $span) {
            $validator->errors()->add('days_worked', 'Days worked cannot be more than the days in this payroll period.');
        }

        if ((int) $data['overnight_nights'] > $span) {
            $validator->errors()->add('overnight_nights', 'Overnight nights cannot be more than the days in this payroll period.');
        }

        $ids = array_map(intval(...), $data['worked_holiday_ids'] ?? []);

        if ($ids === []) {
            return;
        }

        $outside = Holiday::query()
            ->whereIn('id', $ids)
            ->where(function ($query) use ($start, $end): void {
                $query->whereDate('date', '<', $start->toDateString())
                    ->orWhereDate('date', '>', $end->toDateString());
            })
            ->exists();

        if ($outside) {
            $validator->errors()->add('worked_holiday_ids', 'Choose a holiday inside this payroll period.');
        }
    }
}
