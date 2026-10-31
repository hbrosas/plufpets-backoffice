<?php

namespace App\Actions;

use App\Enums\HolidayType;
use App\Enums\PayBasis;
use App\Enums\PayLineKind;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\PosSaleItemCommission;
use Illuminate\Support\Carbon;

class GeneratePayroll
{
    private const MONTHLY_WORK_DAYS = 26;

    private const HOURS_PER_DAY = 8;

    private const OVERTIME_MULTIPLIER = 1.25;

    /**
     * @return array{
     *     monthly_work_days: int,
     *     hours_per_day: int,
     *     overtime_multiplier: string,
     *     regular_holiday_worked: string,
     *     regular_holiday_unworked: string,
     *     special_holiday_worked: string,
     *     special_holiday_unworked: string
     * }
     */
    public static function assumptions(): array
    {
        return [
            'monthly_work_days' => self::MONTHLY_WORK_DAYS,
            'hours_per_day' => self::HOURS_PER_DAY,
            'overtime_multiplier' => number_format(self::OVERTIME_MULTIPLIER, 2, '.', ''),
            'regular_holiday_worked' => HolidayType::Regular->workedLabel(),
            'regular_holiday_unworked' => HolidayType::Regular->unworkedLabel(),
            'special_holiday_worked' => HolidayType::Special->workedLabel(),
            'special_holiday_unworked' => HolidayType::Special->unworkedLabel(),
        ];
    }

    /**
     * Ordinary days are paid from days worked. Holidays in the period are paid separately
     * under the Labor Code: regular holidays at 200% if worked and 100% if not, special
     * non-working days at 130% if worked and no pay if not. Overtime on an ordinary day
     * is 125% of the hourly basic rate. Late is a proportionate deduction. Commissions
     * come from unrefunded sales in the Philippine payroll period.
     *
     * @param  array{
     *     period_start: string,
     *     period_end: string,
     *     days_worked: string,
     *     late_hours: string,
     *     overtime_hours: string,
     *     deduct_government: bool|int|string,
     *     worked_holiday_ids?: list<int|string>
     * }  $input
     * @return array<string, mixed>
     */
    public function handle(Employee $employee, EmployeeProfile $profile, array $input): array
    {
        $profile->loadMissing('payLines');

        $days = (float) $input['days_worked'];
        $late = (float) $input['late_hours'];
        $overtime = (float) $input['overtime_hours'];
        $deductGovernment = filter_var($input['deduct_government'], FILTER_VALIDATE_BOOLEAN);
        $workedIds = array_map(intval(...), $input['worked_holiday_ids'] ?? []);
        $basis = $profile->pay_basis;
        $compensationDaily = 0.0;
        $compensation = [];
        $benefits = [];
        $basicIndex = null;
        $grossCents = 0;

        foreach ($profile->payLines as $line) {
            if ($line->kind !== PayLineKind::Compensation && $line->kind !== PayLineKind::Benefit) {
                continue;
            }

            $daily = $this->dailyRate((float) ($line->amount ?? 0), $basis);
            $earnedCents = $this->cents($daily * $days);
            $isBasic = $line->kind === PayLineKind::Compensation && strcasecmp(trim($line->name), 'Basic salary') === 0;
            $row = $this->earning(
                $line->name,
                'days',
                $this->money($daily),
                $this->unit($days),
                $this->fromCents($earnedCents),
                $isBasic ? 'Basic salary' : null,
                $isBasic ? 'Regular' : null,
            );

            if ($line->kind === PayLineKind::Compensation) {
                $compensationDaily += $daily;

                if ($isBasic && $basicIndex === null) {
                    $basicIndex = count($compensation);
                }

                $compensation[] = $row;
            } else {
                $benefits[] = $row;
            }

            $grossCents += $earnedCents;
        }

        $basicDaily = $compensationDaily;
        $holiday = $this->holidayEarning($input['period_start'], $input['period_end'], $workedIds, $basicDaily);

        if ($holiday !== null) {
            $insertAt = $basicIndex === null ? count($compensation) : $basicIndex + 1;
            array_splice($compensation, $insertAt, 0, [$holiday['line']]);
            $grossCents += $holiday['cents'];
        }

        $earnings = [...$compensation, ...$benefits];

        $nights = (int) ($input['overnight_nights'] ?? 0);
        $overnightRate = (float) ($profile->overnight_allowance ?? 0);

        if ($nights > 0 || $overnightRate > 0) {
            $overnightCents = $this->cents($overnightRate * $nights);
            $earnings[] = $this->earning(
                'Overnight allowance',
                'nights',
                $this->money($overnightRate),
                $this->unit($nights),
                $this->fromCents($overnightCents),
            );
            $grossCents += $overnightCents;
        }

        $hourly = round($basicDaily / self::HOURS_PER_DAY, 2);
        $overtimeRate = $hourly * self::OVERTIME_MULTIPLIER;
        $overtimeCents = $this->cents($overtimeRate * $overtime);
        $lateCents = $this->cents($hourly * $late);

        $earnings[] = $this->earning(
            'Overtime',
            'overtime',
            $this->money($overtimeRate),
            $this->unit($overtime),
            $this->fromCents($overtimeCents),
        );
        $grossCents += $overtimeCents;

        foreach ($this->commissionLines($employee, $input['period_start'], $input['period_end']) as $line) {
            $earnings[] = $line;
            $grossCents += $this->cents((float) $line['amount']);
        }

        $deductions = [
            $this->earning('Late', 'hours', $this->money($hourly), $this->unit($late), $this->fromCents($lateCents)),
        ];
        $deductionCents = $lateCents;
        $employerShares = [];
        $adjustments = [];
        $temporaryEarnings = [];
        $temporaryDeductions = [];

        foreach ($input['adjustments'] ?? [] as $adjustment) {
            $amountCents = $this->cents((float) $adjustment['amount']);
            $normalized = [
                'name' => $adjustment['name'],
                'side' => $adjustment['side'],
                'amount' => $this->fromCents($amountCents),
            ];
            $adjustments[] = $normalized;

            if ($adjustment['side'] === 'deduction') {
                $temporaryDeductions[] = [$normalized, $amountCents];

                continue;
            }

            $temporaryEarnings[] = $this->earning(
                $adjustment['name'],
                'adjustment',
                $normalized['amount'],
                '1.00',
                $normalized['amount'],
            );
            $grossCents += $amountCents;
        }

        foreach ($profile->payLines as $line) {
            if ($line->kind !== PayLineKind::Government) {
                continue;
            }

            $contribution = $this->money((float) ($line->employer_contribution ?? 0));
            $employerShares[] = $this->earning($line->name, 'government', $contribution, '1.00', $contribution);

            if (! $deductGovernment) {
                continue;
            }

            $employeeCents = $this->cents((float) ($line->employee_contribution ?? 0));
            $employeeAmount = $this->fromCents($employeeCents);
            $deductions[] = $this->earning($line->name, 'government', $employeeAmount, '1.00', $employeeAmount);
            $deductionCents += $employeeCents;
        }

        foreach ($temporaryEarnings as $line) {
            $earnings[] = $line;
        }

        foreach ($temporaryDeductions as [$adjustment, $amountCents]) {
            $deductions[] = $this->earning(
                $adjustment['name'],
                'adjustment',
                $adjustment['amount'],
                '1.00',
                $adjustment['amount'],
            );
            $deductionCents += $amountCents;
        }

        return [
            'period_start' => $input['period_start'],
            'period_end' => $input['period_end'],
            'days_worked' => (string) $input['days_worked'],
            'late_hours' => (string) $input['late_hours'],
            'overtime_hours' => (string) $input['overtime_hours'],
            'overnight_nights' => (string) $nights,
            'deduct_government' => $deductGovernment,
            'worked_holiday_ids' => $workedIds,
            'adjustments' => $adjustments,
            'pay_basis' => $basis->value,
            'monthly_work_days' => $basis === PayBasis::Month ? self::MONTHLY_WORK_DAYS : null,
            'hours_per_day' => self::HOURS_PER_DAY,
            'overtime_multiplier' => number_format(self::OVERTIME_MULTIPLIER, 2, '.', ''),
            'hourly_rate' => $this->money($hourly),
            'earnings' => $earnings,
            'deductions' => $deductions,
            'employer_shares' => $employerShares,
            'gross' => $this->fromCents($grossCents),
            'total_deductions' => $this->fromCents($deductionCents),
            'net' => $this->fromCents($grossCents - $deductionCents),
        ];
    }

    /**
     * @param  list<int>  $workedIds
     * @return array{line: array<string, mixed>, cents: int}|null
     */
    private function holidayEarning(string $periodStart, string $periodEnd, array $workedIds, float $basicDaily): ?array
    {
        $holidays = Holiday::query()
            ->whereDate('date', '>=', $periodStart)
            ->whereDate('date', '<=', $periodEnd)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($holidays->isEmpty()) {
            return null;
        }

        $multiplier = 0.0;
        $cents = 0;

        foreach ($holidays as $holiday) {
            $worked = in_array($holiday->id, $workedIds, true);
            $share = $worked ? $holiday->type->workedMultiplier() : $holiday->type->unworkedMultiplier();
            $multiplier += $share;
            $cents += $this->cents($basicDaily * $share);
        }

        return [
            'line' => $this->earning(
                'Holiday',
                'holiday',
                $this->money($basicDaily),
                $this->unit($multiplier),
                $this->fromCents($cents),
                'Basic salary',
                'Holiday',
            ),
            'cents' => $cents,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function commissionLines(Employee $employee, string $periodStart, string $periodEnd): array
    {
        $from = Carbon::parse($periodStart, 'Asia/Manila')->startOfDay()->utc();
        $to = Carbon::parse($periodEnd, 'Asia/Manila')->endOfDay()->utc();

        $lines = PosSaleItemCommission::query()
            ->where('employee_id', $employee->id)
            ->whereHas('saleItem.sale', function ($query) use ($from, $to): void {
                $query->whereNull('refunded_at')
                    ->whereBetween('sold_at', [$from, $to]);
            })
            ->with('saleItem.sale.branch')
            ->get()
            ->groupBy(fn (PosSaleItemCommission $commission): int => (int) $commission->saleItem->sale->branch_id)
            ->map(function ($group): array {
                $branchName = $group->first()->saleItem->sale->branch?->name;
                $total = $group->sum(fn (PosSaleItemCommission $commission): float => (float) $commission->amount);

                return [
                    'name' => filled($branchName) ? 'Commissions — '.$branchName : 'Commissions',
                    'branch_name' => $branchName ?? '',
                    'amount' => $this->money($total),
                ];
            })
            ->sortBy(fn (array $line): string => mb_strtolower($line['branch_name']))
            ->values();

        if ($lines->isEmpty()) {
            return [$this->commissionLine('Commissions', '0.00')];
        }

        return $lines
            ->map(fn (array $line): array => $this->commissionLine(
                $line['name'],
                $line['amount'],
                $line['branch_name'] !== '' ? $line['branch_name'] : null,
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function commissionLine(string $name, string $amount, ?string $label = null): array
    {
        return $this->earning(
            $name,
            'commission',
            $amount,
            $label === null && $amount === '0.00' ? '0.00' : '1.00',
            $amount,
            $label === null ? null : 'Commissions',
            $label,
        );
    }

    /**
     * @return array{
     *     group: string|null,
     *     label: string|null,
     *     name: string,
     *     basis: string,
     *     rate: string,
     *     unit: string,
     *     daily_rate: string|null,
     *     amount: string
     * }
     */
    private function earning(
        string $name,
        string $basis,
        string $rate,
        string $unit,
        string $amount,
        ?string $group = null,
        ?string $label = null,
    ): array {
        return [
            'group' => $group,
            'label' => $label,
            'name' => $name,
            'basis' => $basis,
            'rate' => $rate,
            'unit' => $unit,
            'daily_rate' => $basis === 'days' ? $rate : null,
            'amount' => $amount,
        ];
    }

    private function unit(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function dailyRate(float $amount, PayBasis $basis): float
    {
        if ($basis === PayBasis::Month) {
            return $amount / self::MONTHLY_WORK_DAYS;
        }

        return $amount;
    }

    private function cents(float $amount): int
    {
        return (int) round(round($amount, 2) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function money(float $amount): string
    {
        return $this->fromCents($this->cents($amount));
    }
}
