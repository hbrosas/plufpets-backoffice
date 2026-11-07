<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePayroll;
use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Http\Requests\DownloadPayrollRunRequest;
use App\Http\Requests\PreparePayrollRunRequest;
use App\Http\Requests\StorePayrollRunRequest;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\PayrollFiling;
use App\Support\PayrollCalendar;
use App\Support\Peso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ZipArchive;

class RunPayrollController extends Controller
{
    public function show(Request $request, GeneratePayroll $generatePayroll): Response|RedirectResponse
    {
        if ($request->boolean('new')) {
            $request->session()->forget('payroll_run');

            return redirect()->route('staff.payroll.run');
        }

        $prepared = $this->preparedFromQuery($request, $generatePayroll);

        return $this->page(
            $prepared,
            null,
            $prepared === null ? $request->session()->get('payroll_run.completed') : null,
        );
    }

    public function redirectToRun(): RedirectResponse
    {
        return redirect()->route('staff.payroll.run');
    }

    public function prepare(PreparePayrollRunRequest $request): RedirectResponse
    {
        $request->session()->forget('payroll_run');

        return redirect()->route('staff.payroll.run', [
            'period_start' => $this->date($request->validated('period_start')),
            'period_end' => $this->date($request->validated('period_end')),
            'deduct_government' => $request->boolean('deduct_government') ? 1 : 0,
            'employee_ids' => array_map(intval(...), $request->validated('employee_ids')),
        ]);
    }

    public function preview(StorePayrollRunRequest $request, GeneratePayroll $generatePayroll): Response
    {
        $validated = $request->validated();
        $built = $this->build($validated, $generatePayroll);
        $ids = array_map(fn (array $row): int => (int) $row['id'], $validated['employees']);

        return $this->page(
            $this->preparedPayload(
                $generatePayroll,
                $this->date($validated['period_start']),
                $this->date($validated['period_end']),
                $ids,
                $request->boolean('deduct_government'),
            ),
            $this->previewPayload($validated, $built),
            null,
        );
    }

    public function finalize(StorePayrollRunRequest $request, GeneratePayroll $generatePayroll): RedirectResponse
    {
        $validated = $request->validated();
        $built = $this->build($validated, $generatePayroll);
        $records = DB::transaction(function () use ($built): array {
            $saved = [];

            foreach ($built as $row) {
                $employee = $row['employee'];
                $profile = $row['profile'];
                $payroll = $row['payroll'];
                $record = EmployeePayroll::query()->create([
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'employee_number' => $employee->employee_number,
                    'period_start' => $payroll['period_start'],
                    'period_end' => $payroll['period_end'],
                    'net' => $payroll['net'],
                    'snapshot' => [
                        'employee_name' => $employee->name,
                        'employee_number' => $employee->employee_number,
                        'job_title' => $profile->job_title,
                        'employment_status' => $profile->employment_status?->label(),
                        'date_hired' => $employee->start_date->toDateString(),
                        'payroll' => $payroll,
                    ],
                ]);

                if ($payroll['cash_advance_ids'] !== []) {
                    PayrollFiling::query()
                        ->where('employee_id', $employee->id)
                        ->whereIn('id', $payroll['cash_advance_ids'])
                        ->whereNull('deducted_payroll_id')
                        ->update(['deducted_payroll_id' => $record->id]);
                }

                $saved[] = $record;
            }

            return $saved;
        });

        $total = $this->sumMoney(array_map(fn (EmployeePayroll $record): string => (string) $record->net, $records));

        $request->session()->put('payroll_run', [
            'completed' => [
                'period_label' => $this->periodLabel($validated['period_start'], $validated['period_end']),
                'total' => $total,
                'total_label' => Peso::format($total),
                'download_all_url' => route('staff.payroll.download-all', [
                    'ids' => array_map(fn (EmployeePayroll $record): int => $record->id, $records),
                ]),
                'employees' => array_map(function (EmployeePayroll $record) use ($built): array {
                    $row = collect($built)->first(fn (array $item): bool => $item['employee']->id === $record->employee_id);

                    return [
                        'id' => $record->id,
                        'employee_id' => $record->employee_id,
                        'name' => $record->employee_name,
                        'employee_number' => $record->employee_number,
                        'gross_label' => Peso::format($row['payroll']['gross']),
                        'deductions_label' => Peso::format($row['payroll']['total_deductions']),
                        'net' => $record->net,
                        'net_label' => Peso::format($record->net),
                        'download_url' => route('staff.payrolls.download', $record),
                    ];
                }, $records),
            ],
        ]);

        return redirect()
            ->route('staff.payroll.run')
            ->with('status', 'Payroll finalized.');
    }

    public function download(DownloadPayrollRunRequest $request): SymfonyResponse
    {
        $ids = array_map(intval(...), $request->validated('ids'));
        $payrolls = EmployeePayroll::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (EmployeePayroll $payroll): int|false => array_search($payroll->id, $ids, true))
            ->values();
        $letterhead = Business::current()?->letterhead() ?? [];
        $path = tempnam(sys_get_temp_dir(), 'payroll');
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);
        $used = [];

        foreach ($payrolls as $payroll) {
            $snapshot = $payroll->snapshot;
            abort_unless(is_array($snapshot['payroll'] ?? null), 404);

            $filename = 'payroll-'.$payroll->employee_number.'-'.$payroll->period_start->toDateString().'.pdf';

            if (isset($used[$filename])) {
                $filename = 'payroll-'.$payroll->employee_number.'-'.$payroll->period_start->toDateString().'-'.$payroll->id.'.pdf';
            }

            $used[$filename] = true;
            $zip->addFromString($filename, Pdf::loadView('staff.payroll', [
                'employeeName' => (string) ($snapshot['employee_name'] ?? $payroll->employee_name),
                'jobTitle' => $snapshot['job_title'] ?? null,
                'employmentStatus' => $snapshot['employment_status'] ?? null,
                'dateHired' => $snapshot['date_hired'] ?? null,
                'payroll' => $snapshot['payroll'],
                'letterhead' => $letterhead,
            ])->output());
        }

        $zip->close();
        $contents = file_get_contents($path) ?: '';
        unlink($path);
        $start = $payrolls->first()?->period_start?->toDateString() ?? 'payroll';
        $end = $payrolls->first()?->period_end?->toDateString() ?? 'payroll';
        $filename = 'payroll-'.$start.'-to-'.$end.'.zip';

        return response($contents, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function page(?array $prepared, ?array $preview, mixed $completed): Response
    {
        return Inertia::render('Staff/Payroll/Run', [
            'employees' => $this->choices(),
            'prepared' => $prepared,
            'preview' => $preview,
            'completed' => $completed,
        ]);
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<string, mixed>
     */
    private function preparedPayload(GeneratePayroll $generatePayroll, string $start, string $end, array $employeeIds, bool $deductGovernment): array
    {
        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->where('is_active', true)
            ->whereHas('profile')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $holidays = Holiday::query()
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $items = $generatePayroll->periodItems($employees, $start, $end);
        $holidayDates = $holidays
            ->map(fn (Holiday $holiday): string => $holiday->date->toDateString())
            ->all();
        $workedDates = PayrollCalendar::ordinaryDates($start, $end, $holidayDates);
        $filings = $this->calendarFilings($employees->pluck('id')->all(), $start, $end);

        return [
            'period_start' => $start,
            'period_end' => $end,
            'period_label' => $this->periodLabel($start, $end),
            'deduct_government' => $deductGovernment,
            'employee_ids' => $employees->pluck('id')->all(),
            'calendar' => array_map(function (array $day) use ($filings): array {
                $day['filings'] = $filings[$day['date']] ?? [];

                return $day;
            }, PayrollCalendar::days($start, $end, $holidays)),
            'holidays' => $holidays->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->toDateString(),
                'type_label' => $holiday->type->label(),
            ])->all(),
            'employees' => $employees->map(function (Employee $employee) use ($items, $workedDates): array {
                $item = $items[$employee->id];

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_number' => $employee->employee_number,
                    'days_worked' => count($workedDates),
                    'worked_dates' => $workedDates,
                    'overnight_nights' => $item['overnight_nights'],
                    'commissions' => $this->moneyRows($item['commissions']),
                    'commission_total' => Peso::format($this->sumMoney(array_column($item['commissions'], 'amount'))),
                    'cash_advances' => $this->moneyRows($item['cash_advances']),
                    'cash_advance_total' => Peso::format($this->sumMoney(array_column($item['cash_advances'], 'amount'))),
                ];
            })->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array{employee: Employee, profile: EmployeeProfile, payroll: array<string, mixed>}>  $built
     * @return array<string, mixed>
     */
    private function previewPayload(array $validated, array $built): array
    {
        $total = $this->sumMoney(array_map(fn (array $row): string => $row['payroll']['net'], $built));
        $letterhead = Business::current()?->letterhead() ?? [];

        return [
            'period_start' => $this->date($validated['period_start']),
            'period_end' => $this->date($validated['period_end']),
            'period_label' => $this->periodLabel($validated['period_start'], $validated['period_end']),
            'deduct_government' => filter_var($validated['deduct_government'], FILTER_VALIDATE_BOOLEAN),
            'draft' => $this->draft($validated),
            'total' => $total,
            'total_label' => Peso::format($total),
            'employees' => array_map(fn (array $row): array => [
                'id' => $row['employee']->id,
                'name' => $row['employee']->name,
                'employee_number' => $row['employee']->employee_number,
                'net' => $row['payroll']['net'],
                'net_label' => Peso::format($row['payroll']['net']),
                'gross_label' => Peso::format($row['payroll']['gross']),
                'deductions_label' => Peso::format($row['payroll']['total_deductions']),
                'preview_html' => view('staff.payroll', $this->document($row['employee'], $row['profile'], $row['payroll'], $letterhead))->render(),
            ], $built),
        ];
    }

    /**
     * The attendance step is a normal page address, so a refresh keeps the same period.
     *
     * @return array<string, mixed>|null
     */
    private function preparedFromQuery(Request $request, GeneratePayroll $generatePayroll): ?array
    {
        if (! $request->hasAny(['period_start', 'period_end', 'employee_ids'])) {
            return null;
        }

        $form = PreparePayrollRunRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $this->preparedPayload(
            $generatePayroll,
            $this->date($form->validated('period_start')),
            $this->date($form->validated('period_end')),
            array_map(intval(...), $form->validated('employee_ids')),
            $form->boolean('deduct_government'),
        );
    }

    /**
     * Pending and approved leaves and overnights for the employees in this run.
     * An overnight covers one day for each filed night, starting on its start date.
     *
     * @param  list<int>  $employeeIds
     * @return array<string, list<array{id: int, employee_id: int, employee: string, type: string, type_label: string, status: string, status_label: string, reason: ?string, detail: string}>>
     */
    private function calendarFilings(array $employeeIds, string $start, string $end): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $filings = PayrollFiling::query()
            ->with('employee:id,name')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', [PayrollFilingStatus::Pending, PayrollFilingStatus::Approved])
            ->whereIn('type', [PayrollFilingType::Leave, PayrollFilingType::Overnight])
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($leave) use ($start, $end): void {
                    $leave->where('type', PayrollFilingType::Leave)
                        ->whereDate('starts_on', '<=', $end)
                        ->whereDate('ends_on', '>=', $start);
                })->orWhere(function ($overnight) use ($start, $end): void {
                    $overnight->where('type', PayrollFilingType::Overnight)
                        ->whereDate('starts_on', '<=', $end)
                        ->whereDate('starts_on', '>=', Carbon::parse($start)->subDays(30)->toDateString());
                });
            })
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();
        $byDate = [];

        foreach ($filings as $filing) {
            foreach ($this->filingDates($filing, $start, $end) as $date) {
                $byDate[$date][] = [
                    'id' => $filing->id,
                    'employee_id' => (int) $filing->employee_id,
                    'employee' => (string) $filing->employee?->name,
                    'type' => $filing->type->value,
                    'type_label' => $filing->type->label(),
                    'status' => $filing->status->value,
                    'status_label' => $filing->status->label(),
                    'reason' => $filing->reason,
                    'detail' => $filing->detail(),
                ];
            }
        }

        return $byDate;
    }

    /**
     * @return list<string>
     */
    private function filingDates(PayrollFiling $filing, string $periodStart, string $periodEnd): array
    {
        $periodStart = Carbon::parse($periodStart)->startOfDay();
        $periodEnd = Carbon::parse($periodEnd)->startOfDay();
        $start = $filing->starts_on->copy()->startOfDay();
        $end = $filing->type === PayrollFilingType::Overnight
            ? $start->copy()->addDays(max((int) $filing->nights, 1) - 1)
            : ($filing->ends_on ?? $filing->starts_on)->copy()->startOfDay();
        $cursor = $start->greaterThan($periodStart) ? $start->copy() : $periodStart->copy();
        $last = $end->lessThan($periodEnd) ? $end->copy() : $periodEnd->copy();
        $dates = [];

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return list<array{id: int, name: string, employee_number: string}>
     */
    private function choices(): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->whereHas('profile')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{employee: Employee, profile: EmployeeProfile, payroll: array<string, mixed>}>
     */
    private function build(array $validated, GeneratePayroll $generatePayroll): array
    {
        $rows = $validated['employees'];
        $employees = Employee::query()
            ->with(['profile.payLines'])
            ->whereIn('id', array_map(fn (array $row): int => (int) $row['id'], $rows))
            ->get()
            ->keyBy('id');
        $built = [];

        foreach ($rows as $row) {
            $employee = $employees->get((int) $row['id']);
            $profile = $employee?->profile;
            abort_if($employee === null || $profile === null, 404);

            $built[] = [
                'employee' => $employee,
                'profile' => $profile,
                'payroll' => $generatePayroll->handle($employee, $profile, [
                    'period_start' => $this->date($validated['period_start']),
                    'period_end' => $this->date($validated['period_end']),
                    'days_worked' => (string) $row['days_worked'],
                    'late_hours' => (string) $row['late_hours'],
                    'overtime_hours' => (string) $row['overtime_hours'],
                    'overnight_nights' => (string) $row['overnight_nights'],
                    'deduct_government' => filter_var($validated['deduct_government'], FILTER_VALIDATE_BOOLEAN),
                    'worked_holiday_ids' => $row['worked_holiday_ids'] ?? [],
                    'adjustments' => $row['adjustments'] ?? [],
                ]),
            ];
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function draft(array $validated): array
    {
        return array_map(function (array $row): array {
            $draft = [
                'id' => (int) $row['id'],
                'days_worked' => (string) $row['days_worked'],
                'late_hours' => (string) $row['late_hours'],
                'overtime_hours' => (string) $row['overtime_hours'],
                'overnight_nights' => (string) $row['overnight_nights'],
                'worked_holiday_ids' => array_map(intval(...), $row['worked_holiday_ids'] ?? []),
                'adjustments' => array_values($row['adjustments'] ?? []),
            ];

            if (is_array($row['worked_dates'] ?? null)) {
                $draft['worked_dates'] = array_values($row['worked_dates']);
            }

            return $draft;
        }, $validated['employees']);
    }

    /**
     * @param  array<string, mixed>  $payroll
     * @param  array<string, mixed>  $letterhead
     * @return array<string, mixed>
     */
    private function document(Employee $employee, EmployeeProfile $profile, array $payroll, array $letterhead): array
    {
        return [
            'employeeName' => $employee->name,
            'jobTitle' => $profile->job_title,
            'employmentStatus' => $profile->employment_status?->label(),
            'dateHired' => $employee->start_date->toDateString(),
            'payroll' => $payroll,
            'letterhead' => $letterhead,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function moneyRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $row['amount_label'] = Peso::format($row['amount']);

            return $row;
        }, $rows);
    }

    private function periodLabel(string $start, string $end): string
    {
        return Carbon::parse($start)->format('M j, Y').' – '.Carbon::parse($end)->format('M j, Y');
    }

    private function date(string $value): string
    {
        return Carbon::parse($value)->toDateString();
    }

    /**
     * @param  list<string|int|float>  $amounts
     */
    private function sumMoney(array $amounts): string
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            $cents += (int) round(((float) $amount) * 100);
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
