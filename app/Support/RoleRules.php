<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Validation\Rule;

class RoleRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::keys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a role name.',
            'name.unique' => 'That role name is already used.',
            'permissions.array' => 'Choose permissions for this role.',
            'permissions.*.in' => 'Choose a valid permission.',
        ];
    }
}
