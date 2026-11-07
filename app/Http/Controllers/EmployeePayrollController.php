<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePayroll;
use App\Http\Requests\GeneratePayrollRequest;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\PayrollFiling;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EmployeePayrollController extends Controller
{
    public function store(GeneratePayrollRequest $request, Employee $employee): RedirectResponse
    {
        $employee->profile()->firstOrFail();

        return redirect()
            ->route('staff.record.show', $this->query($employee, $request))
            ->with('status', 'Payroll generated.');
    }

    public function finalize(GeneratePayrollRequest $request, Employee $employee, GeneratePayroll $generatePayroll): RedirectResponse
    {
        $profile = $employee->profile()->with('payLines')->firstOrFail();
        $payroll = $generatePayroll->handle($employee, $profile, $request->validated());

        DB::transaction(function () use ($employee, $profile, $payroll): void {
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
        });

        return redirect()
            ->route('staff.record.show', $this->query($employee, $request))
            ->with('status', 'Payroll finalized.');
    }

    public function download(GeneratePayrollRequest $request, Employee $employee, GeneratePayroll $generatePayroll): Response
    {
        $profile = $employee->profile()->with('payLines')->firstOrFail();
        $payroll = $generatePayroll->handle($employee, $profile, $request->validated());

        return $this->pdf(
            $employee->name,
            $profile->job_title,
            $profile->employment_status?->label(),
            $employee->start_date->toDateString(),
            $payroll,
            'payroll-'.$employee->employee_number.'.pdf',
        );
    }

    public function downloadFinalized(EmployeePayroll $employeePayroll): Response
    {
        $snapshot = $employeePayroll->snapshot;
        $employeePayroll->loadMissing('employee.profile');
        $employee = $employeePayroll->employee;

        return $this->pdf(
            (string) ($snapshot['employee_name'] ?? $employeePayroll->employee_name),
            $snapshot['job_title'] ?? null,
            array_key_exists('employment_status', $snapshot)
                ? $snapshot['employment_status']
                : $employee?->profile?->employment_status?->label(),
            array_key_exists('date_hired', $snapshot)
                ? $snapshot['date_hired']
                : $employee?->start_date?->toDateString(),
            $snapshot['payroll'],
            'payroll-'.$employeePayroll->employee_number.'-'.$employeePayroll->period_start->toDateString().'.pdf',
        );
    }

    /**
     * @param  array<string, mixed>  $payroll
     */
    private function pdf(string $name, ?string $jobTitle, ?string $employmentStatus, ?string $dateHired, array $payroll, string $filename): Response
    {
        return Pdf::loadView('staff.payroll', [
            'employeeName' => $name,
            'jobTitle' => $jobTitle,
            'employmentStatus' => $employmentStatus,
            'dateHired' => $dateHired,
            'payroll' => $payroll,
            'letterhead' => Business::current()?->letterhead() ?? [],
        ])->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function query(Employee $employee, GeneratePayrollRequest $request): array
    {
        return [
            'employee' => $employee,
            'period_start' => $request->validated('period_start'),
            'period_end' => $request->validated('period_end'),
            'days_worked' => $request->validated('days_worked'),
            'late_hours' => $request->validated('late_hours'),
            'overtime_hours' => $request->validated('overtime_hours'),
            'overnight_nights' => $request->validated('overnight_nights'),
            'deduct_government' => $request->boolean('deduct_government') ? 1 : 0,
            'worked_holiday_ids' => array_map(intval(...), $request->validated('worked_holiday_ids')),
            'adjustments' => $request->validated('adjustments'),
        ];
    }
}
