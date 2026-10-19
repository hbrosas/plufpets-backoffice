<?php

namespace App\Support;

use App\Models\Employee;

class EmployeeSession
{
    public const KEY = 'employee';

    /**
     * The signed-in employee stored in the session.
     *
     * Sessions created before staff accounts existed have no id. Those keep
     * full access until the person signs in again. A session with an id always
     * uses that employee's current role.
     *
     * @return array{id: int|null, employee_number: string, name: string, role: string|null, permissions: array<int, string>}|null
     */
    public static function current(): ?array
    {
        $request = request();
        $cacheKey = 'employee.resolved';

        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey)['value'];
        }

        $employee = self::resolve();
        $request->attributes->set($cacheKey, ['value' => $employee]);

        return $employee;
    }

    public static function check(): bool
    {
        return self::current() !== null;
    }

    public static function allows(string $permission): bool
    {
        $employee = self::current();

        if ($employee === null) {
            return false;
        }

        return in_array($permission, $employee['permissions'], true);
    }

    /**
     * @return array{id: int|null, employee_number: string, name: string, role: string|null, permissions: array<int, string>}|null
     */
    private static function resolve(): ?array
    {
        $stored = session(self::KEY);

        if (! is_array($stored)) {
            return null;
        }

        if (isset($stored['id'])) {
            $employee = Employee::query()->with(['role.permissions' => function ($query): void {
                $query->orderBy('sort_order');
            }])->find($stored['id']);

            if (! $employee instanceof Employee || ! $employee->is_active) {
                return null;
            }

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
                'role' => $employee->role?->name,
                'permissions' => $employee->role?->permissions->pluck('key')->values()->all() ?? [],
            ];
        }

        return [
            'id' => null,
            'employee_number' => (string) ($stored['employee_number'] ?? ''),
            'name' => (string) ($stored['name'] ?? ''),
            'role' => isset($stored['role']) ? (string) $stored['role'] : null,
            'permissions' => PermissionCatalog::keys(),
        ];
    }
}
