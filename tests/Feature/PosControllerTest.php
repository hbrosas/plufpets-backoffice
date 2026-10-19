<?php

namespace Tests\Feature;

use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PosCashMovement;
use App\Models\PosCategory;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_pos(): void
    {
        $branch = Branch::factory()->create();

        $this->get("/backoffice/pos/register/{$branch->id}")->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_the_register(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $posCategory = PosCategory::factory()->create(['name' => 'Treats']);
        $shopCategory = Category::factory()->create([
            'name' => 'Dry Food',
            'pos_category_id' => $posCategory->id,
        ]);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Kibble',
            'category_id' => $shopCategory->id,
        ]))->create([
            'name' => 'Large',
            'price' => 199,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Index')
            ->where('branch.name', 'Makati Branch')
            ->where('shift', null)
            ->where('variants.0.product_name', 'Kibble')
            ->where('variants.0.variant_name', 'Large')
            ->where('variants.0.shop_category_name', 'Dry Food')
            ->where('variants.0.pos_category_id', $posCategory->id)
            ->where('variants.0.quantity', 0)
            ->where('variants.0.modifiers', [])
            ->has('openOrders', 0)
            ->has('printers', 0));
    }

    public function test_register_loads_printers_for_the_open_branch(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        Printer::factory()->bluetooth()->for($branch)->create(['name' => 'Makati receipt']);
        Printer::factory()->bluetooth()->for($otherBranch)->create(['name' => 'QC receipt']);

        $response = $this->actingAsEmployee()->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('printers', 1)
            ->where('printers.0.name', 'Makati receipt')
            ->where('printers.0.branch_id', $branch->id));
    }

    public function test_open_shift_is_shared_across_employees(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create([
            'branch_id' => $branch->id,
            'opened_by_employee_number' => '10001',
            'opened_by_name' => 'Sample Employee',
        ]);

        $response = $this->withSession([
            'employee' => [
                'employee_number' => '20002',
                'name' => 'Night Cashier',
            ],
        ])->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('shift.opened_by_name', 'Sample Employee'));
    }

    public function test_open_pos_lists_branch_status_for_the_launcher(): void
    {
        $openBranch = Branch::factory()->create(['name' => 'Greenheights']);
        $closedBranch = Branch::factory()->create(['name' => 'San Pedro']);
        PosShift::factory()->create([
            'branch_id' => $openBranch->id,
            'opened_by_name' => 'Sample Employee',
        ]);
        PosShift::factory()->closed()->create([
            'branch_id' => $closedBranch->id,
            'closed_by_name' => 'Night Cashier',
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('posBranches.0.name', 'Greenheights')
            ->where('posBranches.0.status', 'Open')
            ->where('posBranches.0.user', 'Sample Employee')
            ->where('posBranches.0.href', route('pos.index', $openBranch))
            ->where('posBranches.1.name', 'San Pedro')
            ->where('posBranches.1.status', 'Close')
            ->where('posBranches.1.user', 'Night Cashier'));
    }

    public function test_register_lists_todays_sales_cash_movements_and_starting_cash(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1500,
            'opened_by_name' => 'Sample Employee',
            'opened_at' => now()->subHours(3),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashIn,
            'amount' => 200,
            'reason' => 'Change fund',
            'employee_name' => 'Sample Employee',
            'recorded_at' => now()->subHours(2),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashOut,
            'amount' => 50,
            'reason' => 'Supplier',
            'employee_name' => 'Sample Employee',
            'recorded_at' => now()->subHour(),
        ]);
        $sale = PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'number' => 'POS-20261018-0001',
            'total' => 250,
            'sold_by_name' => 'Sample Employee',
            'sold_at' => now(),
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('todayTransactionsTotal', '₱250.00')
            ->where('todayTransactions.0.title', 'POS-20261018-0001')
            ->where('todayTransactions.0.type', 'sale')
            ->where('todayTransactions.0.id', $sale->id)
            ->where('todayTransactions.1.title', 'Cash out')
            ->where('todayTransactions.1.amount_label', '-₱50.00')
            ->where('todayTransactions.2.title', 'Cash in')
            ->where('todayTransactions.2.detail', 'Change fund')
            ->where('todayTransactions.3.title', 'Starting cash')
            ->where('todayTransactions.3.amount_label', '₱1,500.00'));
    }

    public function test_register_partial_reload_skips_the_product_catalog(): void
    {
        $branch = Branch::factory()->create();
        CatalogItemVariant::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->withHeaders([
                'X-Inertia-Partial-Data' => 'shift,todayTransactions,todayTransactionsTotal,openOrders,flash',
            ])
            ->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Index')
            ->has('shift.id')
            ->missing('variants')
            ->missing('categories')
            ->missing('paymentMethods')
            ->missing('deliveryMethods')
            ->missing('taxFees'));
    }
}
