<?php

namespace Tests\Feature;

use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PosShift;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosCashMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_record_cash_movement(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/cash-movements", [
            'type' => PosCashMovementType::CashIn->value,
            'amount' => '50',
            'reason' => 'Change fund',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/cash-movements", []);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'type' => 'Choose cash in or cash out.',
            'amount' => 'Enter an amount.',
            'reason' => 'Enter a reason.',
        ]);
    }

    public function test_cash_out_is_recorded(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/cash-movements", [
                'type' => PosCashMovementType::CashOut->value,
                'amount' => '200',
                'reason' => 'Petty cash',
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('open_drawer', true);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashOut->value,
            'amount' => '200.00',
            'reason' => 'Petty cash',
        ]);
    }

    public function test_guest_cannot_record_a_drawer_open(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/drawer-opens");

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_open_drawer_requires_the_drawer_permission(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $role = Role::factory()->withPermissions(['pos.access'])->create();
        $employee = Employee::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAsEmployee($employee)
            ->post("/backoffice/pos/register/{$branch->id}/drawer-opens");

        $response->assertForbidden();
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_open_drawer_requires_an_open_shift(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/drawer-opens");

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'status' => 'Open a shift before opening the drawer.',
        ]);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_open_drawer_is_stored_on_todays_transactions(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 500,
            'opened_at' => now()->subHour(),
        ]);

        $response = $this->actingAsEmployee()
            ->post("/backoffice/pos/register/{$branch->id}/drawer-opens");

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionMissing('open_drawer');
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::OpenDrawer->value,
            'amount' => '0.00',
            'reason' => '',
            'employee_number' => '10001',
            'employee_name' => 'Sample Employee',
        ]);
        $this->assertSame('500.00', $shift->expectedDrawerCash());

        $page = $this->actingAsEmployee()->get("/backoffice/pos/register/{$branch->id}");

        $page->assertOk();
        $page->assertInertia(fn (Assert $page) => $page
            ->where('todayTransactions.0.title', 'Open drawer')
            ->where('todayTransactions.0.type', 'open_drawer')
            ->where('todayTransactions.0.amount_label', '')
            ->where('todayTransactions.0.actor_name', 'Sample Employee')
            ->where('todayTransactions.0.detail', null)
            ->where('todayTransactions.1.title', 'Starting cash')
            ->where('todayTransactions.1.amount_label', '₱500.00'));
    }
}
