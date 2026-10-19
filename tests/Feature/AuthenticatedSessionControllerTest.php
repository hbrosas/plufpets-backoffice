<?php

namespace Tests\Feature;

use App\Enums\EmployeeHomeScreen;
use App\Models\Employee;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_sees_the_login_page(): void
    {
        $response = $this->get('/backoffice');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $response = $this->from('/backoffice')->post('/backoffice/login', []);

        $response->assertRedirect('/backoffice');
        $response->assertSessionHasErrors([
            'employee_number' => 'Enter your employee number.',
            'pin' => 'Enter your PIN code.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_short_pin_is_rejected(): void
    {
        $response = $this->from('/backoffice')->post('/backoffice/login', [
            'employee_number' => '10001',
            'pin' => '12',
        ]);

        $response->assertRedirect('/backoffice');
        $response->assertSessionHasErrors([
            'pin' => 'The PIN code must be 4 to 8 digits.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_incorrect_credentials_are_rejected(): void
    {
        $response = $this->from('/backoffice')->post('/backoffice/login', [
            'employee_number' => '10001',
            'pin' => '0000',
        ]);

        $response->assertRedirect('/backoffice');
        $response->assertSessionHasErrors([
            'employee_number' => 'Employee number or PIN code is incorrect.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_correct_credentials_start_a_session(): void
    {
        $response = $this->post('/backoffice/login', [
            'employee_number' => '10001',
            'pin' => '1234',
        ]);

        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('employee.id', $employee->id);

        $this->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employee.employee_number', '10001')
                ->where('employee.name', 'Sample Employee')
                ->where('employee.permissions', PermissionCatalog::keys()));
    }

    public function test_pos_first_employee_opens_the_branch_picker(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create([
            'pin' => '1234',
            'role_id' => $role->id,
            'home_screen' => EmployeeHomeScreen::Pos,
        ]);

        $response = $this->post('/backoffice/login', [
            'employee_number' => $employee->employee_number,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('pos.launch'));
    }

    public function test_signed_in_pos_first_employee_is_sent_to_the_branch_picker(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create([
            'role_id' => $role->id,
            'home_screen' => EmployeeHomeScreen::Pos,
        ]);

        $response = $this->actingAsEmployee($employee)->get('/backoffice');

        $response->assertRedirect(route('pos.launch'));
    }

    public function test_pos_first_employee_without_register_access_opens_the_dashboard(): void
    {
        $role = Role::factory()->withPermissions(['dashboard.view'])->create();
        $employee = Employee::factory()->create([
            'pin' => '1234',
            'role_id' => $role->id,
            'home_screen' => EmployeeHomeScreen::Pos,
        ]);

        $response = $this->post('/backoffice/login', [
            'employee_number' => $employee->employee_number,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_first_employee_without_dashboard_access_opens_the_branch_picker(): void
    {
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create([
            'pin' => '1234',
            'role_id' => $role->id,
            'home_screen' => EmployeeHomeScreen::Dashboard,
        ]);

        $response = $this->post('/backoffice/login', [
            'employee_number' => $employee->employee_number,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('pos.launch'));
    }

    public function test_inactive_employee_cannot_sign_in(): void
    {
        Employee::query()->where('employee_number', '10001')->update(['is_active' => false]);

        $response = $this->from('/backoffice')->post('/backoffice/login', [
            'employee_number' => '10001',
            'pin' => '1234',
        ]);

        $response->assertRedirect('/backoffice');
        $response->assertSessionHasErrors([
            'employee_number' => 'Employee number or PIN code is incorrect.',
        ]);
        $response->assertSessionMissing('employee');
    }

    public function test_inactive_employee_session_is_signed_out(): void
    {
        $employee = Employee::query()->where('employee_number', '10001')->firstOrFail();
        $employee->update(['is_active' => false]);

        $response = $this->actingAsEmployee($employee)->get('/backoffice/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_credentials_with_surrounding_spaces_start_a_session(): void
    {
        $response = $this->post('/backoffice/login', [
            'employee_number' => ' 10001 ',
            'pin' => ' 1234 ',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(
            'employee.id',
            Employee::query()->where('employee_number', '10001')->value('id'),
        );
    }

    public function test_authenticated_employee_is_redirected_away_from_login(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->get('/backoffice');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_logout_ends_the_session(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->post('/backoffice/logout');

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('employee');

        $this->get('/backoffice/dashboard')->assertRedirect(route('login'));
    }

    public function test_repeated_login_attempts_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/backoffice')->post('/backoffice/login', [
                'employee_number' => '10001',
                'pin' => '0000',
            ])->assertRedirect('/backoffice');
        }

        $this->from('/backoffice')->post('/backoffice/login', [
            'employee_number' => '10001',
            'pin' => '0000',
        ])->assertTooManyRequests();
    }
}
