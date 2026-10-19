<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;

class AccessSetup
{
    /**
     * Install the permission catalog, the administrator role, and the first staff account.
     */
    public static function install(): void
    {
        foreach (self::permissions() as $index => $permission) {
            Permission::query()->updateOrCreate(
                ['key' => $permission['key']],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'sort_order' => $index,
                ],
            );
        }

        $administrator = Role::query()->firstOrCreate(['name' => 'Administrator']);
        $administrator->permissions()->sync(Permission::query()->pluck('id'));

        if (Employee::query()->where('employee_number', '10001')->exists()) {
            return;
        }

        Employee::query()->create([
            'name' => 'Sample Employee',
            'date_of_birth' => '1990-01-01',
            'start_date' => '2026-01-01',
            'employee_number' => 10001,
            'pin' => '1234',
            'role_id' => $administrator->id,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<int, array{key: string, name: string, group: string}>
     */
    private static function permissions(): array
    {
        return PermissionCatalog::all();
    }
}
