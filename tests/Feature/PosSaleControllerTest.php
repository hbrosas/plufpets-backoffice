<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\Modifier;
use App\Models\PaymentMethod;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_record_a_sale(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/sales", [
            'items' => [['catalog_item_variant_id' => 1, 'quantity' => 1]],
            'payments' => [['payment_method_id' => 1, 'amount' => '1']],
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_sale_is_rejected_without_an_open_shift(): void
    {
        $branch = Branch::factory()->create();
        $variant = CatalogItemVariant::factory()->create();
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '100',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'status' => 'Open a shift before recording a sale.',
        ]);
    }

    public function test_sale_decrements_product_stock(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product, 'name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 2]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '200',
                    'amount_tendered' => '250',
                ]],
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('open_drawer', true);
        $response->assertSessionHas('checkout_success', [
            'total' => '₱200.00',
            'change' => '₱50.00',
        ]);
        $this->assertDatabaseHas('pos_sales', [
            'branch_id' => $branch->id,
            'total' => '200.00',
            'is_cash' => true,
            'change_due' => '50.00',
        ]);
        $this->assertDatabaseHas('pos_sale_payments', [
            'payment_method_id' => $payment->id,
            'amount' => '200.00',
            'amount_tendered' => '250.00',
            'change_due' => '50.00',
            'is_cash' => true,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_inertia_sale_returns_checkout_success(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product, 'name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Pos/Index',
                'X-Inertia-Partial-Data' => 'shift,todayTransactions,todayTransactionsTotal,openOrders,flash',
            ])
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '100',
                ]],
            ]);

        $sale = PosSale::query()->first();

        $response->assertOk();
        $response->assertJsonPath('component', 'Pos/Index');
        $response->assertJsonPath('props.flash.status', 'Sale '.$sale->number.' recorded.');
        $response->assertJsonPath('props.flash.checkout_success.total', '₱100.00');
        $response->assertJsonPath('props.flash.checkout_success.change', '₱0.00');
        $response->assertJsonPath('props.flash.open_drawer', true);
    }

    public function test_json_sale_returns_checkout_success_without_a_redirect(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product, 'name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->postJson("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '100',
                ]],
            ]);

        $sale = PosSale::query()->first();

        $response->assertOk();
        $response->assertJsonPath('status', 'Sale '.$sale->number.' recorded.');
        $response->assertJsonPath('checkout_success.total', '₱100.00');
        $response->assertJsonPath('checkout_success.change', '₱0.00');
        $response->assertJsonPath('open_drawer', true);
        $receipt = $response->json('receipt');
        $this->assertIsString($receipt);
        $this->assertStringContainsString($sale->number, $receipt);
        $this->assertStringContainsString('Kibble Large', $receipt);
        $this->assertStringContainsString('1  P100.00', $receipt);
        $this->assertStringContainsString('Total', $receipt);
        $this->assertStringContainsString('Tendered Amount', $receipt);
        $this->assertStringContainsString('Change', $receipt);
        $this->assertStringContainsString('Cash', $receipt);
        $this->assertStringNotContainsString("\n\n", $receipt);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
        ]);
    }

    public function test_sale_preview_returns_a_receipt_without_recording_a_sale(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product, 'name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->postJson("/backoffice/pos/register/{$branch->id}/sales/preview", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '150',
                ]],
            ]);

        $response->assertOk();
        $response->assertJsonPath('number', 'POS-'.now()->format('Ymd').'-0001');
        $response->assertJsonPath('open_drawer', true);
        $response->assertJsonPath('checkout_success.total', '₱100.00');
        $response->assertJsonPath('checkout_success.change', '₱50.00');
        $this->assertStringContainsString('Kibble Large', (string) $response->json('receipt'));
        $this->assertStringContainsString('Cash', (string) $response->json('receipt'));
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
    }

    public function test_sale_keeps_the_number_printed_on_the_receipt(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();
        $number = 'POS-'.now()->format('Ymd').'-0007';

        $this->actingAsEmployee()
            ->postJson("/backoffice/pos/register/{$branch->id}/sales", [
                'number' => $number,
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '100',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('pos_sales', [
            'branch_id' => $branch->id,
            'number' => $number,
        ]);
    }

    public function test_sale_is_rejected_when_stock_is_insufficient(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 2]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'amount_tendered' => '100',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors('items.0.quantity');
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_another_employee_can_transact_on_an_open_shift(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create([
            'branch_id' => $branch->id,
            'opened_by_employee_number' => '10001',
        ]);
        $variant = CatalogItem::factory()->service()->create(['name' => 'Grooming'])
            ->variants()->create([
                'name' => 'Standard',
                'price' => 350,
                'is_active' => true,
            ]);
        $payment = PaymentMethod::factory()->create(['name' => 'GCash', 'is_cash' => false]);

        $response = $this->withSession([
            'employee' => [
                'employee_number' => '20002',
                'name' => 'Night Cashier',
            ],
        ])->from("/backoffice/pos/register/{$branch->id}")->post("/backoffice/pos/register/{$branch->id}/sales", [
            'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
            'payments' => [[
                'payment_method_id' => $payment->id,
                'amount' => '350',
                'reference_number' => 'GC-1001',
            ]],
        ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('open_drawer', false);
        $this->assertDatabaseHas('pos_sales', [
            'sold_by_employee_number' => '20002',
            'sold_by_name' => 'Night Cashier',
            'is_cash' => false,
            'total' => '350.00',
        ]);
        $this->assertDatabaseHas('pos_sale_payments', [
            'reference_number' => 'GC-1001',
            'is_cash' => false,
            'amount' => '350.00',
        ]);
    }

    public function test_sale_includes_selected_modifier_price_and_notes(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $item = CatalogItem::factory()->create(['name' => 'Kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $wrap = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium'],
        ]);
        $item->modifiers()->attach($wrap->id);
        CatalogItemModifierOptionPrice::factory()->create([
            'catalog_item_id' => $item->id,
            'modifier_id' => $wrap->id,
            'option' => 'Premium',
            'price' => 25,
        ]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [[
                    'catalog_item_variant_id' => $variant->id,
                    'quantity' => 2,
                    'notes' => 'Ribbon please',
                    'modifiers' => [
                        ['modifier_id' => $wrap->id, 'option' => 'Premium'],
                    ],
                ]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '250',
                    'reference_number' => 'REF-250',
                ]],
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $this->assertDatabaseHas('pos_sale_items', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'notes' => 'Ribbon please',
            'unit_price' => '125.00',
            'line_total' => '250.00',
        ]);
    }

    public function test_unknown_modifier_option_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $item = CatalogItem::factory()->create();
        $variant = CatalogItemVariant::factory()->for($item)->create(['price' => 50]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $wrap = Modifier::factory()->create(['options' => ['Standard']]);
        $item->modifiers()->attach($wrap->id);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [[
                    'catalog_item_variant_id' => $variant->id,
                    'quantity' => 1,
                    'modifiers' => [
                        ['modifier_id' => $wrap->id, 'option' => 'Deluxe'],
                    ],
                ]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '50',
                    'reference_number' => 'REF-50',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors('items.0.modifiers');
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_charging_a_parked_order_removes_the_open_order(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $order = PosOpenOrder::factory()->create(['branch_id' => $branch->id]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'reference_number' => 'REF-100',
                ]],
                'pos_open_order_id' => $order->id,
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $this->assertDatabaseMissing('pos_open_orders', ['id' => $order->id]);
    }

    public function test_sale_cannot_clear_an_open_order_from_another_branch(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $order = PosOpenOrder::factory()->create(['branch_id' => $other->id]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'reference_number' => 'REF-100',
                ]],
                'pos_open_order_id' => $order->id,
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors('pos_open_order_id');
        $this->assertDatabaseHas('pos_open_orders', ['id' => $order->id]);
    }

    public function test_sales_receive_sequential_numbers_for_the_day(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItem::factory()->service()->create()
            ->variants()->create([
                'name' => 'Standard',
                'price' => 100,
                'is_active' => true,
            ]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);
        $prefix = now()->format('Ymd');

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'reference_number' => 'REF-1',
                ]],
            ])
            ->assertRedirect(route('pos.index', $branch));

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                    'reference_number' => 'REF-2',
                ]],
            ])
            ->assertRedirect(route('pos.index', $branch));

        $this->assertDatabaseHas('pos_sales', ['number' => "POS-{$prefix}-0001"]);
        $this->assertDatabaseHas('pos_sales', ['number' => "POS-{$prefix}-0002"]);
    }

    public function test_charge_sale_is_rejected_without_a_reference_number(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'payments.0.reference_number' => 'Enter the reference number.',
        ]);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_cash_sale_is_rejected_without_amount_tendered(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '100',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'payments.0.amount_tendered' => 'Enter the amount tendered.',
        ]);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_split_payments_open_the_drawer_when_any_payment_is_cash(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $cash = PaymentMethod::factory()->cash()->create(['name' => 'Cash']);
        $charge = PaymentMethod::factory()->create(['name' => 'GCash', 'is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [
                    [
                        'payment_method_id' => $cash->id,
                        'amount' => '40',
                        'amount_tendered' => '40',
                    ],
                    [
                        'payment_method_id' => $charge->id,
                        'amount' => '60',
                        'reference_number' => 'GC-60',
                    ],
                ],
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('open_drawer', true);
        $this->assertDatabaseHas('pos_sales', [
            'total' => '100.00',
            'is_cash' => true,
            'payment_method_name' => 'Cash + GCash',
        ]);
        $this->assertDatabaseCount('pos_sale_payments', 2);
        $this->assertSame('40.00', PosShift::query()->first()->cashSalesTotal());
    }

    public function test_payments_that_do_not_equal_the_sale_total_are_rejected(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create(['price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->create(['is_cash' => false]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [['catalog_item_variant_id' => $variant->id, 'quantity' => 1]],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '50',
                    'reference_number' => 'REF-50',
                ]],
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'payments' => 'Payments must equal the sale total.',
        ]);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_guest_cannot_read_a_sale_receipt(): void
    {
        $sale = PosSale::factory()->create();

        $response = $this->get("/backoffice/pos/register/{$sale->branch_id}/sales/{$sale->id}/receipt");

        $response->assertRedirect(route('login'));
    }

    public function test_sale_receipt_returns_the_saved_receipt(): void
    {
        $branch = Branch::factory()->create();
        $sale = PosSale::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'POS-20261019-0004',
            'total' => 100,
            'amount_tendered' => 100,
            'change_due' => 0,
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'product_name' => 'Kibble',
            'variant_name' => 'Large',
            'quantity' => 1,
            'line_total' => 100,
        ]);
        PosSalePayment::factory()->create([
            'pos_sale_id' => $sale->id,
            'payment_method_name' => 'Cash',
            'reference_number' => null,
        ]);

        $response = $this->actingAsEmployee()
            ->getJson("/backoffice/pos/register/{$branch->id}/sales/{$sale->id}/receipt");

        $response->assertOk();
        $receipt = $response->json('receipt');
        $this->assertIsString($receipt);
        $this->assertStringContainsString('POS-20261019-0004', $receipt);
        $this->assertStringContainsString('Kibble Large', $receipt);
        $this->assertStringContainsString('Cash', $receipt);
    }

    public function test_sale_receipt_is_not_found_for_another_branch(): void
    {
        $branch = Branch::factory()->create();
        $sale = PosSale::factory()->create();

        $this->actingAsEmployee()
            ->getJson("/backoffice/pos/register/{$branch->id}/sales/{$sale->id}/receipt")
            ->assertNotFound();
    }
}
