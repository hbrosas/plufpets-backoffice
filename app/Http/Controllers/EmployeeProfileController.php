<?php

namespace App\Http\Controllers;

use App\Actions\GeneratePayroll;
use App\Enums\CatalogItemType;
use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PayBasis;
use App\Enums\PayLineKind;
use App\Http\Requests\UpdateEmployeeProfileRequest;
use App\Models\CatalogItem;
use App\Models\Employee;
use App\Models\EmployeePayLine;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeServiceCommission;
use App\Models\Holiday;
use App\Support\PayrollRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeProfileController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/Records/Index', [
            'records' => Employee::query()
                ->whereHas('profile')
                ->with('profile')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_number' => $employee->employee_number,
                    'job_title' => $employee->profile?->job_title ?? '',
                    'employment_status' => $employee->profile?->employment_status?->label() ?? '',
                    'mobile' => $employee->profile?->mobile ?? '',
                ]),
        ]);
    }

    public function store(Employee $employee): RedirectResponse
    {
        $profile = $employee->profile()->firstOrCreate([], [
            'pay_basis' => PayBasis::Month,
        ]);

        return redirect()
            ->route('staff.record.show', $employee)
            ->with('status', $profile->wasRecentlyCreated ? 'Employee record created.' : 'Employee record opened.');
    }

    public function show(Request $request, Employee $employee, GeneratePayroll $generatePayroll): Response
    {
        $profile = $employee->profile()->with('payLines')->firstOrFail();
        $employee->load('serviceCommissions');

        return Inertia::render('Staff/Record', [
            'staff' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_number' => $employee->employee_number,
                'date_of_birth' => $employee->date_of_birth->toDateString(),
                'start_date' => $employee->start_date->toDateString(),
            ],
            'profile' => $this->profilePayload($profile, $employee),
            'services' => CatalogItem::query()
                ->where('type', CatalogItemType::Service)
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (CatalogItem $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                ]),
            'genders' => $this->options(Gender::cases()),
            'civilStatuses' => $this->options(CivilStatus::cases()),
            'employmentStatuses' => $this->options(EmploymentStatus::cases()),
            'payBases' => $this->options(PayBasis::cases()),
            'payrollAssumptions' => GeneratePayroll::assumptions(),
            'payroll' => $this->payrollFromQuery($request, $employee, $profile, $generatePayroll),
            'holidays' => Holiday::query()
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'name' => $holiday->name,
                    'type' => $holiday->type->value,
                    'type_label' => $holiday->type->label(),
                    'worked_label' => $holiday->type->workedLabel(),
                    'unworked_label' => $holiday->type->unworkedLabel(),
                ]),
            'finalizedPayrolls' => EmployeePayroll::query()
                ->whereBelongsTo($employee)
                ->orderByDesc('id')
                ->get()
                ->map(fn (EmployeePayroll $payroll): array => [
                    'id' => $payroll->id,
                    'period_start' => $payroll->period_start->toDateString(),
                    'period_end' => $payroll->period_end->toDateString(),
                    'net' => $payroll->net,
                ]),
        ]);
    }

    public function update(UpdateEmployeeProfileRequest $request, Employee $employee): RedirectResponse
    {
        $profile = $employee->profile()->firstOrFail();
        $data = $request->validated();

        DB::transaction(function () use ($employee, $profile, $data): void {
            $profile->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'civil_status' => $data['civil_status'],
                'address' => $data['address'],
                'mobile' => $data['mobile'],
                'email' => $data['email'],
                'tin' => $data['tin'],
                'sss' => $data['sss'],
                'philhealth' => $data['philhealth'],
                'pagibig' => $data['pagibig'],
                'job_title' => $data['job_title'],
                'employment_status' => $data['employment_status'],
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_relationship' => $data['emergency_contact_relationship'],
                'emergency_contact_phone' => $data['emergency_contact_phone'],
                'pay_basis' => $data['pay_basis'],
                'overnight_allowance' => $data['overnight_allowance'],
            ]);

            $profile->payLines()->delete();
            $this->storePayLines($profile, $data['compensation_lines'], PayLineKind::Compensation);
            $this->storePayLines($profile, $data['benefit_lines'], PayLineKind::Benefit);
            $this->storeGovernmentLines($profile, $data['government_lines']);

            $employee->serviceCommissions()->delete();

            foreach ($data['commissions'] as $commission) {
                if ((float) $commission['assigned_rate'] === 0.0 && (float) $commission['assisted_rate'] === 0.0) {
                    continue;
                }

                $employee->serviceCommissions()->create([
                    'catalog_item_id' => $commission['catalog_item_id'],
                    'assigned_rate' => $commission['assigned_rate'],
                    'assisted_rate' => $commission['assisted_rate'],
                ]);
            }
        });

        return redirect()
            ->route('staff.record.show', $employee)
            ->with('status', 'Employee record saved.');
    }

    private function payrollFromQuery(Request $request, Employee $employee, EmployeeProfile $profile, GeneratePayroll $generatePayroll): ?array
    {
        if (! $request->exists('period_start')) {
            return null;
        }

        $validator = Validator::make(
            PayrollRules::prepare($request->query()),
            PayrollRules::for(),
            PayrollRules::messages(),
        );
        $validator->after(function (ValidatorContract $validator): void {
            PayrollRules::after($validator);
        });

        if ($validator->fails()) {
            return null;
        }

        return $generatePayroll->handle($employee, $profile, $validator->validated());
    }

    /**
     * @param  list<array{name: string, amount: mixed}>  $lines
     */
    private function storePayLines(EmployeeProfile $profile, array $lines, PayLineKind $kind): void
    {
        foreach ($lines as $index => $line) {
            $profile->payLines()->create([
                'kind' => $kind,
                'name' => $line['name'],
                'amount' => $line['amount'],
                'sort' => $index,
            ]);
        }
    }

    /**
     * @param  list<array{name: string, employee_contribution: mixed, employer_contribution: mixed}>  $lines
     */
    private function storeGovernmentLines(EmployeeProfile $profile, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $profile->payLines()->create([
                'kind' => PayLineKind::Government,
                'name' => $line['name'],
                'employee_contribution' => $line['employee_contribution'],
                'employer_contribution' => $line['employer_contribution'],
                'sort' => $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(EmployeeProfile $profile, Employee $employee): array
    {
        $lines = $profile->payLines;

        return [
            'first_name' => $profile->first_name ?? '',
            'middle_name' => $profile->middle_name ?? '',
            'last_name' => $profile->last_name ?? '',
            'gender' => $profile->gender?->value ?? '',
            'civil_status' => $profile->civil_status?->value ?? '',
            'address' => $profile->address ?? '',
            'mobile' => $profile->mobile ?? '',
            'email' => $profile->email ?? '',
            'tin' => $profile->tin ?? '',
            'sss' => $profile->sss ?? '',
            'philhealth' => $profile->philhealth ?? '',
            'pagibig' => $profile->pagibig ?? '',
            'job_title' => $profile->job_title ?? '',
            'employment_status' => $profile->employment_status?->value ?? '',
            'emergency_contact_name' => $profile->emergency_contact_name ?? '',
            'emergency_contact_relationship' => $profile->emergency_contact_relationship ?? '',
            'emergency_contact_phone' => $profile->emergency_contact_phone ?? '',
            'pay_basis' => $profile->pay_basis->value,
            'overnight_allowance' => $profile->overnight_allowance,
            'compensation_lines' => $lines
                ->filter(fn (EmployeePayLine $line): bool => $line->kind === PayLineKind::Compensation)
                ->values()
                ->map(fn (EmployeePayLine $line): array => [
                    'name' => $line->name,
                    'amount' => $line->amount,
                ])
                ->all(),
            'benefit_lines' => $lines
                ->filter(fn (EmployeePayLine $line): bool => $line->kind === PayLineKind::Benefit)
                ->values()
                ->map(fn (EmployeePayLine $line): array => [
                    'name' => $line->name,
                    'amount' => $line->amount,
                ])
                ->all(),
            'government_lines' => $lines
                ->filter(fn (EmployeePayLine $line): bool => $line->kind === PayLineKind::Government)
                ->values()
                ->map(fn (EmployeePayLine $line): array => [
                    'name' => $line->name,
                    'employee_contribution' => $line->employee_contribution ?? '',
                    'employer_contribution' => $line->employer_contribution ?? '',
                ])
                ->all(),
            'commissions' => $employee->serviceCommissions
                ->map(fn (EmployeeServiceCommission $commission): array => [
                    'catalog_item_id' => $commission->catalog_item_id,
                    'assigned_rate' => $commission->assigned_rate,
                    'assisted_rate' => $commission->assisted_rate,
                ])
                ->all(),
        ];
    }

    /**
     * @param  list<Gender|CivilStatus|EmploymentStatus|PayBasis>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (Gender|CivilStatus|EmploymentStatus|PayBasis $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }
}
