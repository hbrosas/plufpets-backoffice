<?php

namespace Tests\Feature;

use App\Enums\EmployeeHomeScreen;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_staff(): void
    {
        $response = $this->get('/backoffice/staff');

        $response->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_is_forbidden(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/staff');

        $response->assertForbidden();
    }

    public function test_staff_page_lists_employees(): void
    {
        $response = $this->actingAsEmployee()->get('/backoffice/staff');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Index')
            ->where('employees.0.employee_number', '10001')
            ->where('employees.0.name', 'Sample Employee')
            ->where('employees.0.home_screen', 'dashboard'));
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', []);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'name' => 'Enter a name.',
            'date_of_birth' => 'Enter a date of birth.',
            'start_date' => 'Enter a start date.',
            'pin' => 'Enter a PIN code.',
            'role_id' => 'Choose a role.',
            'home_screen' => 'Choose where sign-in opens.',
        ]);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_start_date_before_birth_is_rejected(): void
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '1990-01-01',
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'start_date' => 'Start date must be on or after the date of birth.',
        ]);
        $this->assertDatabaseMissing('employees', ['name' => 'Mina Cruz']);
    }

    public function test_future_date_of_birth_is_rejected(): void
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => now()->addDay()->toDateString(),
            'start_date' => now()->addDays(2)->toDateString(),
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'date_of_birth' => 'Date of birth must be in the past.',
        ]);
    }

    public function test_short_pin_is_rejected(): void
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '2026-02-01',
            'pin' => '12',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'pin' => 'The PIN code must be 4 to 8 digits.',
        ]);
    }

    public function test_staff_account_is_created_with_the_next_empid(): void
    {
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $response = $this->actingAsEmployee()->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '2026-02-01',
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
            'employee_number' => '99999',
        ]);

        $response->assertRedirect(route('staff.index'));
        $response->assertSessionHas('status', 'Staff account created. EMPID 10002.');

        $employee = Employee::query()->where('name', 'Mina Cruz')->firstOrFail();

        $this->assertSame('10002', $employee->employee_number);
        $this->assertNotSame('4321', $employee->pin);
        $this->assertTrue(Hash::check('4321', $employee->pin));
        $this->assertSame($role->id, $employee->role_id);
        $this->assertSame(EmployeeHomeScreen::Dashboard, $employee->home_screen);
    }

    public function test_blank_pin_keeps_the_current_pin(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee = Employee::factory()->create([
            'pin' => '1111',
            'role_id' => $admin->role_id,
        ]);

        $response = $this->actingAsEmployee($admin)->put("/backoffice/staff/{$employee->id}", [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1995-04-12',
            'start_date' => '2024-06-01',
            'pin' => '',
            'role_id' => $admin->role_id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertTrue(Hash::check('1111', $employee->fresh()->pin));
        $this->assertSame('Mina Cruz', $employee->fresh()->name);
    }

    public function test_new_pin_is_used_for_sign_in(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee = Employee::factory()->create([
            'pin' => '1111',
            'role_id' => $admin->role_id,
        ]);

        $this->actingAsEmployee($admin)->put("/backoffice/staff/{$employee->id}", [
            'name' => $employee->name,
            'date_of_birth' => '1995-04-12',
            'start_date' => '2024-06-01',
            'pin' => '2222',
            'role_id' => $admin->role_id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ])->assertRedirect(route('staff.index'));

        $this->post('/backoffice/logout');

        $this->from('/backoffice')->post('/backoffice/login', [
            'employee_number' => $employee->employee_number,
            'pin' => '1111',
        ])->assertSessionHasErrors('employee_number');

        $this->post('/backoffice/login', [
            'employee_number' => $employee->employee_number,
            'pin' => '2222',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_signed_in_employee_cannot_be_deleted(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();

        $response = $this->actingAsEmployee($admin)->delete("/backoffice/staff/{$admin->id}");

        $response->assertRedirect(route('staff.index'));
        $response->assertSessionHas('status', 'You cannot delete your own account.');
        $this->assertModelExists($admin);
    }

    public function test_only_staff_manager_cannot_be_moved_off_staff_management(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $cashier = Role::factory()->withPermissions(['sales.view'])->create();

        $response = $this->actingAsEmployee($admin)->from('/backoffice/staff')->put("/backoffice/staff/{$admin->id}", [
            'name' => $admin->name,
            'date_of_birth' => '1990-01-01',
            'start_date' => '2026-01-01',
            'role_id' => $cashier->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'role_id' => 'Keep staff management on at least one active employee.',
        ]);
        $this->assertSame($admin->role_id, $admin->fresh()->role_id);
    }

    public function test_another_staff_account_can_be_deleted(): void
    {
        $admin = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($admin)->delete("/backoffice/staff/{$employee->id}");

        $response->assertRedirect(route('staff.index'));
        $this->assertModelMissing($employee);
    }

    public function test_pos_first_is_saved_for_a_register_role(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();

        $response = $this->actingAsEmployee()->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '2026-02-01',
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'pos',
        ]);

        $response->assertRedirect(route('staff.index'));
        $this->assertSame(
            EmployeeHomeScreen::Pos,
            Employee::query()->where('name', 'Mina Cruz')->firstOrFail()->home_screen,
        );
    }

    public function test_pos_first_is_rejected_when_the_role_cannot_use_the_register(): void
    {
        $role = Role::factory()->withPermissions(['dashboard.view'])->create();

        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '2026-02-01',
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'pos',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'home_screen' => 'POS first needs a role that can use the register.',
        ]);
        $this->assertDatabaseMissing('employees', ['name' => 'Mina Cruz']);
    }

    public function test_dashboard_first_is_rejected_when_the_role_cannot_view_the_dashboard(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();

        $response = $this->actingAsEmployee()->from('/backoffice/staff')->post('/backoffice/staff', [
            'name' => 'Mina Cruz',
            'date_of_birth' => '1998-03-04',
            'start_date' => '2026-02-01',
            'pin' => '4321',
            'role_id' => $role->id,
            'is_active' => true,
            'home_screen' => 'dashboard',
        ]);

        $response->assertRedirect('/backoffice/staff');
        $response->assertSessionHasErrors([
            'home_screen' => 'Dashboard first needs a role that can view the dashboard.',
        ]);
        $this->assertDatabaseMissing('employees', ['name' => 'Mina Cruz']);
    }
}
