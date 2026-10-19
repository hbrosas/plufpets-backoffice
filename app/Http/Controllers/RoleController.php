<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Staff/Roles/Index', [
            'roles' => Role::query()
                ->with(['permissions' => function ($query): void {
                    $query->select('permissions.id', 'permissions.key')->orderBy('sort_order');
                }])
                ->withCount('employees')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('key')->values()->all(),
                    'employee_count' => $role->employees_count,
                ]),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create($request->safe()->only(['name']));
        $this->syncPermissions($role, $request->validated('permissions'));

        return redirect()
            ->route('staff.roles.index')
            ->with('status', 'Role created.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->safe()->only(['name']));
        $this->syncPermissions($role, $request->validated('permissions'));

        return redirect()
            ->route('staff.roles.index')
            ->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->employees()->exists()) {
            return redirect()
                ->route('staff.roles.index')
                ->with('status', 'This role is assigned to staff, so it cannot be deleted.');
        }

        $role->delete();

        return redirect()
            ->route('staff.roles.index')
            ->with('status', 'Role deleted.');
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function syncPermissions(Role $role, array $keys): void
    {
        $permissionIds = Permission::query()->whereIn('key', $keys)->pluck('id');

        $role->permissions()->sync($permissionIds);
    }

    /**
     * @return array<int, array{group: string, permissions: array<int, array{key: string, name: string}>}>
     */
    private function permissionGroups(): array
    {
        $groups = [];

        foreach (Permission::query()->orderBy('sort_order')->orderBy('id')->get() as $permission) {
            $groups[$permission->group]['group'] = $permission->group;
            $groups[$permission->group]['permissions'][] = [
                'key' => $permission->key,
                'name' => $permission->name,
            ];
        }

        return array_values($groups);
    }
}
