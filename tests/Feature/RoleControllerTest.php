<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_roles(): void
    {
        $response = $this->get('/backoffice/staff/roles');

        $response->assertRedirect(route('login'));
    }

    public function test_employee_without_role_access_is_forbidden(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/staff/roles');

        $response->assertForbidden();
    }

    public function test_roles_page_lists_permissions(): void
    {
        $response = $this->actingAsEmployee()->get('/backoffice/staff/roles');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Roles/Index')
            ->where('roles.0.name', 'Administrator')
            ->has('permissionGroups', 5));
    }

    public function test_empty_role_name_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/staff/roles')->post('/backoffice/staff/roles', [
            'name' => '',
            'permissions' => [],
        ]);

        $response->assertRedirect('/backoffice/staff/roles');
        $response->assertSessionHasErrors(['name' => 'Enter a role name.']);
    }

    public function test_role_is_created_with_selected_permissions(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/staff/roles', [
            'name' => 'Cashier',
            'permissions' => ['pos.access', 'sales.view'],
        ]);

        $response->assertRedirect(route('staff.roles.index'));

        $role = Role::query()->where('name', 'Cashier')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['pos.access', 'sales.view'],
            $role->permissions()->pluck('key')->all(),
        );
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/staff/roles')->post('/backoffice/staff/roles', [
            'name' => 'Administrator',
            'permissions' => ['pos.access'],
        ]);

        $response->assertRedirect('/backoffice/staff/roles');
        $response->assertSessionHasErrors(['name' => 'That role name is already used.']);
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/staff/roles')->post('/backoffice/staff/roles', [
            'name' => 'Cashier',
            'permissions' => ['pos.access', 'not-a-permission'],
        ]);

        $response->assertRedirect('/backoffice/staff/roles');
        $response->assertSessionHasErrors('permissions.1');
        $this->assertDatabaseMissing('roles', ['name' => 'Cashier']);
    }

    public function test_last_staff_management_permission_cannot_be_removed(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();
        $permissions = array_values(array_filter(
            PermissionCatalog::keys(),
            fn (string $key): bool => $key !== 'staff.manage',
        ));

        $response = $this->actingAsEmployee($admin)->from('/backoffice/staff/roles')->put("/backoffice/staff/roles/{$role->id}", [
            'name' => 'Administrator',
            'permissions' => $permissions,
        ]);

        $response->assertRedirect('/backoffice/staff/roles');
        $response->assertSessionHasErrors([
            'permissions' => 'Keep staff management on at least one active employee.',
        ]);
        $this->assertTrue($role->permissions()->where('key', 'staff.manage')->exists());
    }

    public function test_role_assigned_to_staff_is_not_deleted(): void
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $response = $this->actingAsEmployee()->delete("/backoffice/staff/roles/{$role->id}");

        $response->assertRedirect(route('staff.roles.index'));
        $response->assertSessionHas('status', 'This role is assigned to staff, so it cannot be deleted.');
        $this->assertModelExists($role);
    }

    public function test_unused_role_is_deleted(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create(['name' => 'Cashier']);

        $response = $this->actingAsEmployee()->delete("/backoffice/staff/roles/{$role->id}");

        $response->assertRedirect(route('staff.roles.index'));
        $this->assertModelMissing($role);
    }
}
