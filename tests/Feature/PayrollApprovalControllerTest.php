<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollFiling;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayrollApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_approvals(): void
    {
        $this->get('/backoffice/staff/approvals')->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_cannot_review_filings(): void
    {
        $employee = Employee::factory()->create();
        $filing = PayrollFiling::factory()->create();

        $this->actingAsEmployee($employee)
            ->get('/backoffice/staff/approvals')
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->put('/backoffice/staff/approvals/'.$filing->id, ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_manager_approves_and_rejects_pending_filings(): void
    {
        $manager = Employee::factory()->create([
            'name' => 'Hazel Brosas',
            'role_id' => Role::factory()->withPermissions(['staff.manage']),
        ]);
        $pending = PayrollFiling::factory()->create();
        $approved = PayrollFiling::factory()->overtime()->create(['status' => 'approved']);

        $this->actingAsEmployee($manager)
            ->get('/backoffice/staff/approvals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Approvals/Index')
                ->where('filters.tab', 'pending')
                ->has('filings.data', 1)
                ->where('filings.data.0.id', $pending->id));

        $this->actingAsEmployee($manager)
            ->put('/backoffice/staff/approvals/'.$pending->id, [
                'status' => 'approved',
                'review_note' => 'Covered',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_filings', [
            'id' => $pending->id,
            'status' => 'approved',
            'reviewed_by_name' => 'Hazel Brosas',
            'review_note' => 'Covered',
        ]);

        $this->actingAsEmployee($manager)
            ->put('/backoffice/staff/approvals/'.$approved->id, ['status' => 'rejected'])
            ->assertNotFound();
    }
}
