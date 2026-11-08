<?php

namespace Tests\Feature;

use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Employee;
use App\Models\PosCashMovement;
use App\Models\PosCategory;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Printer;
use App\Support\SmsDisclaimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $response = $this->actingAsEmployee()->get('/backoffice/pos');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Launch')
            ->where('branches.0.name', 'Greenheights')
            ->where('branches.0.status', 'Open')
            ->where('branches.0.user', 'Sample Employee')
            ->where('branches.0.href', route('pos.index', $openBranch, false))
            ->where('branches.1.name', 'San Pedro')
            ->where('branches.1.status', 'Closed')
            ->where('branches.1.user', 'Night Cashier'));
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

    public function test_guest_is_redirected_from_shop_order_alerts(): void
    {
        $branch = Branch::factory()->create();

        $this->get("/backoffice/pos/register/{$branch->id}/shop-orders")
            ->assertRedirect(route('login'));
    }

    public function test_employee_without_pos_access_cannot_read_shop_order_alerts(): void
    {
        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->getJson("/backoffice/pos/register/{$branch->id}/shop-orders")
            ->assertForbidden();
    }

    public function test_register_lists_shop_orders_placed_after_the_last_seen_one(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        PosOpenOrder::factory()->for($branch)->create([
            'status' => null,
            'saved_by_name' => 'Parked',
        ]);
        $seen = PosOpenOrder::factory()->for($branch)->create([
            'number' => 'OPEN-SEEN',
            'status' => 'placed',
            'saved_by_name' => 'Earlier',
            'total' => 10,
        ]);
        $fresh = PosOpenOrder::factory()->for($branch)->create([
            'number' => 'OPEN-NEW',
            'status' => 'placed',
            'saved_by_name' => 'Ada Reyes',
            'total' => 240,
            'items' => [[
                'product_name' => 'Aozi Dog',
                'variant_name' => 'Beef',
                'price' => '80.00',
                'quantity' => 3,
                'notes' => 'Preorder',
            ]],
        ]);
        PosOpenOrder::factory()->for($otherBranch)->create([
            'number' => 'OPEN-OTHER',
            'status' => 'placed',
            'saved_by_name' => 'Other Branch',
        ]);

        $baseline = $this->actingAsEmployee()->getJson("/backoffice/pos/register/{$branch->id}/shop-orders");

        $baseline->assertOk();
        $baseline->assertJsonPath('latest_id', $fresh->id);
        $baseline->assertJsonPath('orders', []);

        $response = $this->actingAsEmployee()->getJson("/backoffice/pos/register/{$branch->id}/shop-orders?after={$seen->id}");

        $response->assertOk();
        $response->assertJsonPath('latest_id', $fresh->id);
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('orders.0.number', 'OPEN-NEW');
        $response->assertJsonPath('orders.0.customer', 'Ada Reyes');
        $response->assertJsonPath('orders.0.total_label', '₱240.00');
        $response->assertJsonPath('orders.0.items.0.product', 'Aozi Dog');
        $response->assertJsonPath('orders.0.items.0.variant', 'Beef');
        $response->assertJsonPath('orders.0.items.0.quantity', 3);
        $response->assertJsonPath('orders.0.items.0.line_total', '₱240.00');
        $response->assertJsonPath('orders.0.items.0.preorder', true);
    }

    public function test_register_lists_online_orders_for_its_branch(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        PosOpenOrder::factory()->for($branch)->create([
            'status' => null,
            'saved_by_name' => 'Parked',
        ]);
        $order = PosOpenOrder::factory()->for($branch)->create([
            'number' => 'OPEN-SHOP',
            'status' => 'placed',
            'saved_by_name' => 'Ada Reyes',
            'phone' => '09171234567',
            'total' => 80,
        ]);
        PosOpenOrder::factory()->for($otherBranch)->create([
            'number' => 'OPEN-OTHER',
            'status' => 'placed',
        ]);

        $response = $this->actingAsEmployee()->getJson("/backoffice/pos/register/{$branch->id}/online-orders");

        $response->assertOk();
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('orders.0.id', $order->id);
        $response->assertJsonPath('orders.0.number', 'OPEN-SHOP');
        $response->assertJsonPath('orders.0.customer', 'Ada Reyes');
        $response->assertJsonPath('orders.0.status', 'placed');
        $response->assertJsonPath('statuses.0.value', 'placed');
        $response->assertJsonPath('statuses.4.value', 'cancelled');
    }

    public function test_online_orders_separate_preorder_lines_from_items_ready_to_sell(): void
    {
        $branch = Branch::factory()->create();
        PosOpenOrder::factory()->for($branch)->create([
            'status' => 'placed',
            'items' => [
                [
                    'catalog_item_variant_id' => 11,
                    'product_name' => 'Kibble',
                    'variant_name' => 'Large',
                    'price' => '80.00',
                    'quantity' => 1,
                    'notes' => null,
                    'is_product' => true,
                    'modifiers' => [],
                ],
                [
                    'catalog_item_variant_id' => 12,
                    'product_name' => 'Aozi Dog',
                    'variant_name' => 'Beef',
                    'price' => '75.00',
                    'quantity' => 3,
                    'notes' => 'Preorder',
                    'is_product' => true,
                    'modifiers' => [],
                ],
            ],
        ]);

        $response = $this->actingAsEmployee()->getJson("/backoffice/pos/register/{$branch->id}/online-orders");

        $response->assertOk();
        $response->assertJsonPath('orders.0.lines.0.preorder', false);
        $response->assertJsonPath('orders.0.lines.0.product_name', 'Kibble');
        $response->assertJsonPath('orders.0.lines.1.preorder', true);
        $response->assertJsonPath('orders.0.lines.1.quantity', 3);
        $response->assertJsonPath('orders.0.items.1', 'Aozi Dog · Beef × 3 · Preorder');
        $response->assertJsonPath('orders.0.sale_number', null);
        $response->assertJsonPath('orders.0.sale_id', null);
    }

    public function test_online_order_includes_the_linked_sale_and_status(): void
    {
        $branch = Branch::factory()->create();
        $sale = PosSale::factory()->create([
            'number' => 'POS-PREP1',
        ]);
        PosOpenOrder::factory()->for($branch)->create([
            'status' => 'preparing',
            'pos_sale_id' => $sale->id,
        ]);

        $response = $this->actingAsEmployee()->getJson("/backoffice/pos/register/{$branch->id}/online-orders");

        $response->assertOk();
        $response->assertJsonPath('orders.0.status', 'preparing');
        $response->assertJsonPath('orders.0.status_label', 'Preparing Order');
        $response->assertJsonPath('orders.0.sale_id', $sale->id);
        $response->assertJsonPath('orders.0.sale_number', 'POS-PREP1');
    }

    public function test_register_status_change_texts_the_customer(): void
    {
        Http::preventStrayRequests();
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 1, 'status' => 'Queued'],
            ]),
        ]);
        $branch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($branch)->create([
            'name' => 'Ada Reyes · 09171234567',
            'phone' => '09171234567',
            'status' => 'placed',
            'total' => 80,
            'saved_by_name' => 'Ada Reyes',
        ]);

        $response = $this->actingAsEmployee()->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
            'status' => 'cancelled',
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.status', 'cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('sms_messages', [
            'number' => '09171234567',
            'body' => SmsDisclaimer::append('Hi Ada Reyes, order '.$order->number.' has been cancelled.'),
        ]);
    }

    public function test_creating_a_sale_prepares_the_order_and_saves_an_open_order(): void
    {
        Http::preventStrayRequests();
        config([
            'services.semaphore.key' => 'test-key',
            'services.semaphore.sender' => 'PLUFPETS',
        ]);
        Http::fake([
            'https://api.semaphore.co/api/v4/messages' => Http::response([
                ['message_id' => 1, 'status' => 'Queued'],
            ]),
        ]);
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Treats']))
            ->create(['name' => 'Default', 'price' => 40]);
        $order = PosOpenOrder::factory()->for($branch)->create([
            'number' => 'OPEN-SHOP-1',
            'phone' => '09171234567',
            'status' => 'placed',
            'saved_by_name' => 'Ada Reyes',
            'total' => 80,
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'product_name' => 'Treats',
                'variant_name' => 'Default',
                'price' => '40.00',
                'quantity' => 2,
                'notes' => null,
                'is_product' => true,
                'modifiers' => [],
            ]],
        ]);

        $response = $this->actingAsEmployee()->postJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}/sale");

        $response->assertOk();
        $response->assertJsonPath('order.status', 'preparing');
        $response->assertJsonPath('open_order.name', 'OPEN-SHOP-1');
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertNull($order->fresh()->pos_sale_id);
        $ticket = PosOpenOrder::query()->whereNull('status')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($order->id, $ticket->checkout['online_order_id']);
        $this->assertSame(2, $ticket->items[0]['quantity']);
        $this->assertSame('Treats', $ticket->items[0]['product_name']);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('sms_messages', [
            'number' => '09171234567',
            'body' => SmsDisclaimer::append('Hi Ada Reyes, we are preparing order OPEN-SHOP-1.'),
        ]);

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$branch->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('openOrders', 1)
                ->where('openOrders.0.name', 'OPEN-SHOP-1'));
    }

    public function test_creating_a_sale_rejects_a_preorder_and_a_closed_shift(): void
    {
        $branch = Branch::factory()->create();
        $variant = CatalogItemVariant::factory()->create();
        $preorder = PosOpenOrder::factory()->for($branch)->create([
            'status' => 'placed',
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'product_name' => 'Treats',
                'variant_name' => 'Default',
                'price' => '40.00',
                'quantity' => 1,
                'notes' => 'Preorder',
                'is_product' => true,
                'modifiers' => [],
            ]],
        ]);

        $this->actingAsEmployee()
            ->postJson("/backoffice/pos/register/{$branch->id}/online-orders/{$preorder->id}/sale")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'A pre-order cannot be turned into a sale.']);

        $this->assertSame('placed', $preorder->fresh()->status);
        $this->assertDatabaseCount('pos_open_orders', 1);

        PosShift::factory()->create(['branch_id' => $branch->id]);
        $closed = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($closed)->create([
            'status' => 'placed',
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'product_name' => 'Treats',
                'variant_name' => 'Default',
                'price' => '40.00',
                'quantity' => 1,
                'notes' => null,
                'is_product' => true,
                'modifiers' => [],
            ]],
        ]);

        $this->actingAsEmployee()
            ->postJson("/backoffice/pos/register/{$closed->id}/online-orders/{$order->id}/sale")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Open a shift before parking an order.']);

        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_register_rejects_a_status_that_skips_the_process(): void
    {
        $branch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($branch)->create([
            'status' => 'placed',
        ]);

        $this->actingAsEmployee()
            ->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
                'status' => 'preparing',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'That status is not available for this order.']);

        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_register_can_mark_a_prepared_order_out_for_delivery(): void
    {
        $branch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($branch)->create([
            'status' => 'preparing',
        ]);

        $this->actingAsEmployee()
            ->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
                'status' => 'out_for_delivery',
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'out_for_delivery');

        $this->assertSame('out_for_delivery', $order->fresh()->status);
    }

    public function test_register_cannot_cancel_a_completed_order(): void
    {
        $branch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($branch)->create([
            'status' => 'completed',
        ]);

        $this->actingAsEmployee()
            ->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
                'status' => 'cancelled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'That status is not available for this order.']);

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_register_cannot_update_another_branch_order(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->for($otherBranch)->create([
            'status' => 'placed',
        ]);

        $this->actingAsEmployee()
            ->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
                'status' => 'preparing',
            ])
            ->assertNotFound();

        $this->assertSame('placed', $order->fresh()->status);
    }
}
