<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosLaunchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_branch_picker(): void
    {
        $response = $this->get('/backoffice/pos');

        $response->assertRedirect(route('login'));
    }

    public function test_employee_without_register_access_is_forbidden(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/pos');

        $response->assertForbidden();
    }

    public function test_register_employee_chooses_an_active_branch(): void
    {
        $openBranch = Branch::factory()->create(['name' => 'Greenheights']);
        Branch::factory()->inactive()->create(['name' => 'Closed Shop']);
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)->get('/backoffice/pos');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Launch')
            ->has('branches', 1)
            ->where('branches.0.name', 'Greenheights')
            ->where('branches.0.href', route('pos.index', $openBranch)));
    }
}
