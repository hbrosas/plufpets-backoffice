<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Role;
use App\Support\EmployeeRules;
use App\Support\EmployeeSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return EmployeeRules::for(pinRequired: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return EmployeeRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $pin = $this->string('pin')->trim()->toString();

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'pin' => $pin === '' ? null : $pin,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employee = $this->route('employee');

                if (! $employee instanceof Employee) {
                    return;
                }

                $signedInId = EmployeeSession::current()['id'] ?? null;

                if ($signedInId === $employee->id && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');

                    return;
                }

                $roleGrantsStaffManagement = Role::query()
                    ->whereKey($this->integer('role_id'))
                    ->whereHas('permissions', function ($query): void {
                        $query->where('key', 'staff.manage');
                    })
                    ->exists();

                if ($employee->isOnlyActiveStaffManager() && (! $this->boolean('is_active') || ! $roleGrantsStaffManagement)) {
                    $validator->errors()->add('role_id', 'Keep staff management on at least one active employee.');

                    return;
                }

                EmployeeRules::guardHomeScreen($validator);
            },
        ];
    }
}
