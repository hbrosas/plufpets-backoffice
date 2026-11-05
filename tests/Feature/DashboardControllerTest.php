<?php

namespace Tests\Feature;

use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Employee;
use App\Models\PosSaleItemCommission;
use App\Models\Role;
use App\Models\SmsCreditBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backoffice/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_the_dashboard(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->get('/backoffice/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('employee.employee_number', '10001')
            ->where('employee.name', 'Sample Employee')
            ->where('commissions.scope', 'all')
            ->where('pulse.metrics.0.label', 'Revenue')
            ->where('pulse.metrics.0.today', '₱0.00')
            ->where('stock.low', [])
            ->where('activeStaffCount', Employee::query()->where('is_active', true)->count())
            ->where('smsCreditBalance', null));
    }

    public function test_sms_credit_balance_is_shared_for_an_employee_who_can_view_it(): void
    {
        SmsCreditBalance::factory()->create(['balance' => 1250]);
        $viewer = $this->employeeWith(['dashboard.view', 'sms.balance']);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('smsCreditBalance.balance', 1250)
                ->where('smsCreditBalance.label', '1,250'));
    }

    public function test_sms_credit_balance_is_hidden_without_the_permission(): void
    {
        SmsCreditBalance::factory()->create(['balance' => 1250]);
        $viewer = $this->employeeWith(['dashboard.view']);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('smsCreditBalance', null));
    }

    public function test_viewer_without_sales_access_does_not_receive_sales_or_other_commissions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, 25);
        $this->commission($other, 40);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('pulse', null)
                ->where('stock', null)
                ->where('activeStaffCount', null)
                ->where('commissions.scope', 'self')
                ->where('commissions.report.total', '₱25.00')
                ->has('commissions.report.employees', 1)
                ->where('commissions.report.employees.0.name', 'Ada Groomer'));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard?preset=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('commissions.filters.preset', 'week')
                ->where('commissions.report.total', '₱25.00'));
    }

    public function test_sales_viewer_receives_branch_sales_and_every_commission(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view', 'sales.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, 25);
        $this->commission($other, 40);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse.metrics.0.label', 'Revenue')
                ->where('commissions.scope', 'all')
                ->where('commissions.report.total', '₱65.00')
                ->has('commissions.report.employees', 2)
                ->where('commissions.report.employees.0.name', 'Ben Cashier')
                ->where('stock', null)
                ->where('activeStaffCount', null));
    }

    public function test_staff_manager_sees_every_commission_and_the_active_staff_count(): void
    {
        $manager = $this->employeeWith(['dashboard.view', 'staff.manage'], 'Ada Groomer', '30001');
        Employee::factory()->create();
        Employee::factory()->inactive()->create();

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse', null)
                ->where('commissions.scope', 'all')
                ->where('activeStaffCount', Employee::query()->where('is_active', true)->count()));
    }

    public function test_inventory_viewer_receives_low_stock_without_sales_figures(): void
    {
        $viewer = $this->employeeWith(['dashboard.view', 'inventory.view']);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Bowl'])->id,
            'name' => 'Default',
        ]);
        BranchInventory::factory()->create([
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse', null)
                ->where('stock.low.0.product', 'Bowl')
                ->where('stock.low.0.status', 'Out of stock')
                ->where('stock.low.0.quantity', 0));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function employeeWith(array $permissions, string $name = 'Sample Employee', string $number = '30001'): Employee
    {
        $role = Role::factory()->withPermissions($permissions)->create();

        return Employee::factory()->create([
            'role_id' => $role->id,
            'name' => $name,
            'employee_number' => $number,
        ]);
    }

    private function commission(Employee $employee, float $amount): void
    {
        PosSaleItemCommission::factory()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'amount' => $amount,
        ]);
    }
}
