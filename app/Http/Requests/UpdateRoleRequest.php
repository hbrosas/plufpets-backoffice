<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\RoleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
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
        $role = $this->route('role');

        return RoleRules::for($role instanceof Role ? $role : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RoleRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'permissions' => $this->input('permissions', []),
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

                $role = $this->route('role');
                $permissions = $this->input('permissions', []);

                if (! $role instanceof Role || ! is_array($permissions)) {
                    return;
                }

                if ($role->wouldRemoveLastStaffManagement($permissions)) {
                    $validator->errors()->add(
                        'permissions',
                        'Keep staff management on at least one active employee.',
                    );
                }
            },
        ];
    }
}
