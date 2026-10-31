<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Models\PosShift;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/backoffice/reports/sales')->assertRedirect(route('login'));
        $this->get('/backoffice/reports/sales/export')->assertRedirect(route('login'));
        $this->get('/backoffice/reports/commissions')->assertRedirect(route('login'));
        $this->get('/backoffice/reports/commissions/export')->assertRedirect(route('login'));
        $this->get('/backoffice/reports/inventory')->assertRedirect(route('login'));
        $this->get('/backoffice/reports/payables/export')->assertRedirect(route('login'));
    }

    public function test_sales_report_uses_manila_periods_and_skips_refunded_receipts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $makati = Branch::factory()->create(['name' => 'Makati']);
        Branch::factory()->create(['name' => 'BGC']);

        $this->sale($makati, '2026-06-14 16:00:00', 100);
        $this->sale($makati, '2026-06-15 01:00:00', 50);
        $this->sale($makati, '2026-06-14 15:00:00', 40);
        $this->sale($makati, '2026-06-20 01:00:00', 20);
        $this->sale($makati, '2026-03-15 04:00:00', 80);
        $this->sale($makati, '2026-06-15 02:00:00', 999, refunded: true);

        $this->actingAsEmployee($this->employeeWith(['sales.view']))
            ->get('/backoffice/reports/sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Sales')
                ->where('filters.preset', 'month')
                ->where('sales.summary.collected', '₱210.00')
                ->where('sales.summary.receipts', 4)
                ->where('sales.summary.refunds', '₱999.00')
                ->where('sales.branches.0.name', 'BGC')
                ->where('sales.branches.0.total', '₱0.00')
                ->where('sales.branches.1.name', 'Makati')
                ->where('sales.branches.1.total', '₱210.00'));

        $this->actingAsEmployee($this->employeeWith(['sales.view'], 'Day Viewer', '30011'))
            ->get('/backoffice/reports/sales?preset=day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sales.summary.collected', '₱150.00')
                ->where('sales.summary.receipts', 2)
                ->where('sales.summary.refunds', '₱999.00'));
    }

    public function test_sales_report_counts_services_and_ranks_products_by_quantity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $branch = Branch::factory()->create(['name' => 'Makati']);
        $groom = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->service()->create(['name' => 'Full groom'])->id,
            'name' => 'Default',
        ]);
        $shampoo = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Shampoo'])->id,
            'name' => 'Default',
        ]);
        $treats = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Treats'])->id,
            'name' => 'Default',
        ]);

        $today = $this->sale($branch, '2026-06-15 01:00:00', 400);
        $this->line($today, $groom, 3, 300);
        $this->line($today, $shampoo, 4, 80);

        $earlier = $this->sale($branch, '2026-06-10 01:00:00', 40);
        $this->line($earlier, $groom, 2, 200);
        $this->line($earlier, $treats, 1, 40);

        $refunded = $this->sale($branch, '2026-06-15 02:00:00', 90, refunded: true);
        $this->line($refunded, $groom, 9, 900);
        $this->line($refunded, $shampoo, 8, 160);

        $this->actingAsEmployee($this->employeeWith(['sales.view']))
            ->get('/backoffice/reports/sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Sales')
                ->where('sales.summary.services', 5)
                ->where('sales.items', function ($items) {
                    $shampoo = $items->firstWhere('name', 'Shampoo');
                    $treats = $items->firstWhere('name', 'Treats');

                    return $shampoo['quantity'] === 4
                        && $shampoo['total'] === '₱80.00'
                        && $treats['quantity'] === 1
                        && $treats['total'] === '₱40.00';
                }));

        $this->actingAsEmployee($this->employeeWith(['sales.view'], 'Service Day', '30012'))
            ->get('/backoffice/reports/sales?preset=day')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sales.summary.services', 3));
    }

    public function test_stock_report_lists_products_with_five_or_fewer_on_hand(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati']);
        $shampoo = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Shampoo'])->id,
            'name' => 'Small',
        ]);
        $bowl = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Bowl'])->id,
            'name' => 'Default',
        ]);
        $food = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Food'])->id,
            'name' => 'Default',
        ]);
        $bath = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->service()->create(['name' => 'Bath'])->id,
            'name' => 'Default',
        ]);

        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $shampoo->id,
            'quantity' => 5,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $bowl->id,
            'quantity' => 0,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $food->id,
            'quantity' => 6,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $bath->id,
            'quantity' => 1,
        ]);

        $this->actingAsEmployee($this->employeeWith(['inventory.view']))
            ->get('/backoffice/reports/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Inventory')
                ->where('inventory.items', function ($items) {
                    $bowl = $items->firstWhere('product', 'Bowl');
                    $shampoo = $items->firstWhere('product', 'Shampoo');

                    if ($bowl === null || $shampoo === null) {
                        return false;
                    }

                    return $bowl['quantity'] === 0
                        && $bowl['variant'] === null
                        && $shampoo['quantity'] === 5
                        && $shampoo['variant'] === 'Small'
                        && ! $items->contains(fn (array $item): bool => $item['product'] === 'Bath');
                }));
    }

    public function test_commissions_report_hides_other_employees_from_a_viewer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, '2026-06-14 16:00:00', 25);
        $this->commission($viewer, '2026-06-08 01:00:00', 10);
        $this->commission($viewer, '2026-06-20 01:00:00', 12);
        $this->commission($viewer, '2026-05-20 01:00:00', 7);
        $this->commission($other, '2026-06-15 01:00:00', 40);
        $this->commission($viewer, '2026-06-15 03:00:00', 80, refunded: true);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/reports/commissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Commissions')
                ->where('scope', 'self')
                ->where('report.label', 'This month')
                ->where('report.total', '₱47.00')
                ->has('report.employees', 1)
                ->where('report.employees.0.name', 'Ada Groomer')
                ->where('report.employees.0.amount', '₱47.00'));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/reports/commissions?preset=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.preset', 'week')
                ->where('report.total', '₱25.00'));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/reports/commissions?preset=year')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.total', '₱54.00'));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/reports/commissions?preset=custom&from=2026-06-08&to=2026-06-08')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.label', 'Jun 8, 2026')
                ->where('report.total', '₱10.00'));
    }

    public function test_commissions_report_lists_every_employee_for_a_sales_viewer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['sales.view', 'dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, '2026-06-15 01:00:00', 25);
        $this->commission($other, '2026-06-15 01:00:00', 40);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/reports/commissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'all')
                ->where('report.total', '₱65.00')
                ->has('report.employees', 2)
                ->where('report.employees.0.name', 'Ben Cashier')
                ->where('report.employees.0.amount', '₱40.00')
                ->where('report.employees.1.name', 'Ada Groomer')
                ->where('report.employees.1.amount', '₱25.00'));
    }

    public function test_sales_export_includes_receipts_lines_and_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $makati = Branch::factory()->create(['name' => 'Makati']);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Shampoo'])->id,
            'name' => 'Large',
            'sku' => 'SH-LG',
        ]);
        $sale = $this->sale($makati, '2026-06-15 01:00:00', 80);
        $sale->update(['number' => 'R-100', 'sold_by_name' => 'Ada Groomer', 'payment_method_name' => 'Cash']);
        $this->line($sale, $variant, 2, 80)->update([
            'sku' => 'SH-LG',
            'variant_name' => 'Large',
        ]);
        $this->sale($makati, '2026-06-15 02:00:00', 999, refunded: true);

        $response = $this->actingAsEmployee($this->employeeWith(['sales.view']))
            ->get('/backoffice/reports/sales/export?preset=day');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=sales-2026-06-15-to-2026-06-15.csv', (string) $response->headers->get('content-disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Sales report', $csv);
        $this->assertStringContainsString('Collected', $csv);
        $this->assertStringContainsString('₱80.00', $csv);
        $this->assertStringContainsString('₱999.00', $csv);
        $this->assertStringContainsString('R-100', $csv);
        $this->assertStringContainsString('Shampoo', $csv);
        $this->assertStringContainsString('SH-LG', $csv);
        $this->assertStringContainsString('Ada Groomer', $csv);
        $this->assertStringContainsString('Makati', $csv);
        $this->assertStringContainsString('Sale lines', $csv);
        $this->assertStringContainsString('By payment method', $csv);
    }

    public function test_commission_export_hides_other_employees(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);
        $this->commission($viewer, '2026-06-15 01:00:00', 25);
        $this->commission($other, '2026-06-15 01:00:00', 40);

        $response = $this->actingAsEmployee($viewer)->get('/backoffice/reports/commissions/export?preset=month');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Ada Groomer', $csv);
        $this->assertStringContainsString('25.00', $csv);
        $this->assertStringContainsString('Commission lines', $csv);
        $this->assertStringNotContainsString('Ben Cashier', $csv);
    }

    public function test_custom_commission_range_over_366_days_is_rejected(): void
    {
        $viewer = $this->employeeWith(['dashboard.view']);

        $this->actingAsEmployee($viewer)
            ->from('/backoffice/reports/commissions')
            ->get('/backoffice/reports/commissions?preset=custom&from=2024-01-01&to=2026-01-01')
            ->assertRedirect('/backoffice/reports/commissions')
            ->assertSessionHasErrors(['to' => 'Choose a range of 366 days or less.']);

        $this->actingAsEmployee($viewer)
            ->from('/backoffice/reports/commissions')
            ->get('/backoffice/reports/commissions?preset=custom')
            ->assertSessionHasErrors([
                'from' => 'Choose a start date.',
                'to' => 'Choose an end date.',
            ]);
    }

    private function employeeWith(array $permissions, string $name = 'Sample Employee', string $number = '30001'): Employee
    {
        $role = Role::factory()->withPermissions($permissions)->create();

        return Employee::factory()->create([
            'role_id' => $role->id,
            'name' => $name,
            'employee_number' => $number,
        ]);
    }

    private function sale(Branch $branch, string $soldAt, float $total, bool $refunded = false): PosSale
    {
        $shift = PosShift::query()->where('branch_id', $branch->id)->first()
            ?? PosShift::factory()->create(['branch_id' => $branch->id]);

        return PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'sold_at' => $soldAt,
            'subtotal' => $total,
            'total' => $total,
            'refunded_at' => $refunded ? '2026-06-15 05:00:00' : null,
        ]);
    }

    private function line(PosSale $sale, CatalogItemVariant $variant, int $quantity, float $lineTotal): PosSaleItem
    {
        return PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $quantity,
            'line_total' => $lineTotal,
        ]);
    }

    private function commission(Employee $employee, string $soldAt, float $amount, bool $refunded = false): void
    {
        $branch = Branch::query()->first() ?? Branch::factory()->create();
        $sale = $this->sale($branch, $soldAt, $amount, $refunded);
        $item = PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'line_total' => $amount,
        ]);

        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $item->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'amount' => $amount,
        ]);
    }
}
