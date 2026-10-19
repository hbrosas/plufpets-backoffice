<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/Index', [
            'employees' => Employee::query()
                ->with('role:id,name')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_number' => $employee->employee_number,
                    'date_of_birth' => $employee->date_of_birth->toDateString(),
                    'start_date' => $employee->start_date->toDateString(),
                    'role_id' => $employee->role_id,
                    'role_name' => $employee->role->name,
                    'is_active' => $employee->is_active,
                    'home_screen' => $employee->home_screen->value,
                ]),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::createWithNextNumber($request->safe()->only([
            'name',
            'date_of_birth',
            'start_date',
            'pin',
            'role_id',
            'is_active',
            'home_screen',
        ]));

        return redirect()
            ->route('staff.index')
            ->with('status', "Staff account created. EMPID {$employee->employee_number}.");
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $attributes = $request->safe()->only([
            'name',
            'date_of_birth',
            'start_date',
            'role_id',
            'is_active',
            'home_screen',
        ]);

        if ($request->filled('pin')) {
            $attributes['pin'] = $request->string('pin')->toString();
        }

        $employee->update($attributes);

        return redirect()
            ->route('staff.index')
            ->with('status', 'Staff account updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $signedInId = EmployeeSession::current()['id'] ?? null;

        if ($signedInId === $employee->id) {
            return redirect()
                ->route('staff.index')
                ->with('status', 'You cannot delete your own account.');
        }

        if ($employee->isOnlyActiveStaffManager()) {
            return redirect()
                ->route('staff.index')
                ->with('status', 'This is the only active employee who can manage staff.');
        }

        $employee->delete();

        return redirect()
            ->route('staff.index')
            ->with('status', 'Staff account deleted.');
    }
}
