<?php

namespace App\Http\Controllers;

use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PayBasis;
use App\Http\Requests\UpdateMyEmployeeRecordRequest;
use App\Models\Employee;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MyEmployeeRecordController extends Controller
{
    public function show(): Response
    {
        $employee = $this->employee();

        return Inertia::render('Me/Employee201', [
            'account' => $employee instanceof Employee,
            'staff' => $employee instanceof Employee ? [
                'name' => $employee->name,
                'employee_number' => $employee->employee_number,
                'date_of_birth' => $employee->date_of_birth->toDateString(),
                'start_date' => $employee->start_date->toDateString(),
            ] : null,
            'profile' => $employee instanceof Employee ? $this->profile($employee) : null,
            'genders' => $this->options(Gender::cases()),
            'civilStatuses' => $this->options(CivilStatus::cases()),
            'employmentStatuses' => $this->options(EmploymentStatus::cases()),
        ]);
    }

    public function update(UpdateMyEmployeeRecordRequest $request): RedirectResponse
    {
        $employee = $this->employee();
        abort_unless($employee instanceof Employee, 403);

        $employee->profile()->firstOrCreate([], [
            'pay_basis' => PayBasis::Month,
        ])->update($request->safe()->only(array_keys($request->rules())));

        return back()->with('status', 'Employee 201 saved.');
    }

    private function employee(): ?Employee
    {
        $employeeId = EmployeeSession::current()['id'] ?? null;

        if ($employeeId === null) {
            return null;
        }

        return Employee::query()->find($employeeId);
    }

    /**
     * @return array<string, string|null>
     */
    private function profile(Employee $employee): array
    {
        $profile = $employee->profile;

        return [
            'first_name' => $profile?->first_name ?? '',
            'middle_name' => $profile?->middle_name ?? '',
            'last_name' => $profile?->last_name ?? '',
            'gender' => $profile?->gender?->value ?? '',
            'civil_status' => $profile?->civil_status?->value ?? '',
            'address' => $profile?->address ?? '',
            'mobile' => $profile?->mobile ?? '',
            'email' => $profile?->email ?? '',
            'tin' => $profile?->tin ?? '',
            'sss' => $profile?->sss ?? '',
            'philhealth' => $profile?->philhealth ?? '',
            'pagibig' => $profile?->pagibig ?? '',
            'job_title' => $profile?->job_title ?? '',
            'employment_status' => $profile?->employment_status?->value ?? '',
            'emergency_contact_name' => $profile?->emergency_contact_name ?? '',
            'emergency_contact_relationship' => $profile?->emergency_contact_relationship ?? '',
            'emergency_contact_phone' => $profile?->emergency_contact_phone ?? '',
        ];
    }

    /**
     * @param  list<Gender|CivilStatus|EmploymentStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (Gender|CivilStatus|EmploymentStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }
}
