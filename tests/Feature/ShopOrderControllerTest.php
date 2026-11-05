<?php

namespace Tests\Feature;

use App\Enums\CommissionRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Employee;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Models\PosSalePayment;
use App\Models\PosSaleTax;
use App\Support\SmsDisclaimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_shop_orders(): void
    {
        $response = $this->get('/backoffice/shop/orders');

        $response->assertRedirect(route('login'));
    }

    public function test_employee_without_permission_cannot_open_shop_orders(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAsEmployee($employee)->get('/backoffice/shop/orders');

        $response->assertForbidden();
    }

    public function test_status_change_sends_the_matching_text(): void
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
        $order = PosOpenOrder::factory()->create([
            'name' => 'Ada Reyes · 09171234567',
            'phone' => '09171234567',
            'status' => 'placed',
            'total' => 80,
            'saved_by_name' => 'Ada Reyes',
        ]);

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->put('/backoffice/shop/orders/'.$order->id, [
                'status' => 'preparing',
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertDatabaseHas('sms_messages', [
            'number' => '09171234567',
            'body' => SmsDisclaimer::append('Hi Ada Reyes, we are preparing order '.$order->number.'.'),
        ]);
    }

    public function test_placed_order_is_listed_and_a_parked_ticket_is_not(): void
    {
        PosOpenOrder::factory()->create([
            'number' => 'OPEN-SHOP',
            'status' => 'placed',
            'saved_by_name' => 'Ada Reyes',
            'phone' => '09171234567',
        ]);
        PosOpenOrder::factory()->create([
            'status' => null,
            'saved_by_name' => 'Parked Ticket',
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Orders/Index')
            ->has('orders', 1)
            ->where('orders.0.number', 'OPEN-SHOP')
            ->where('orders.0.customer', 'Ada Reyes')
            ->where('orders.0.phone', '09171234567')
            ->where('orders.0.status', 'placed'));
    }

    public function test_guest_cannot_view_an_order(): void
    {
        $order = PosOpenOrder::factory()->create(['status' => 'placed']);

        $this->get('/backoffice/shop/orders/'.$order->id)->assertRedirect(route('login'));
    }

    public function test_employee_without_permission_cannot_view_an_order(): void
    {
        $employee = Employee::factory()->create();
        $order = PosOpenOrder::factory()->create(['status' => 'placed']);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/shop/orders/'.$order->id)
            ->assertForbidden();
    }

    public function test_a_parked_ticket_cannot_be_opened_as_a_shop_order(): void
    {
        $order = PosOpenOrder::factory()->create(['status' => null]);

        $this->actingAsEmployee()
            ->get('/backoffice/shop/orders/'.$order->id)
            ->assertNotFound();
    }

    public function test_order_details_include_the_checkout_and_the_sale(): void
    {
        $employee = Employee::factory()->create(['name' => 'Robin Santiago']);
        $sale = PosSale::factory()->create([
            'number' => 'POS-1001',
            'sold_by_name' => 'Hazel Brosas',
            'sold_by_employee_number' => '10001',
            'delivery_method_name' => 'Lalamove',
            'delivery_fee' => 150,
            'subtotal' => 225,
            'tax_total' => 27,
            'total' => 302,
            'amount_tendered' => 500,
            'change_due' => 198,
        ]);
        $item = PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'product_name' => 'Aozi Dog',
            'variant_name' => 'Beef',
            'quantity' => 3,
            'unit_price' => 75,
            'line_total' => 225,
            'notes' => 'Preorder',
            'modifiers' => [['name' => 'Cut', 'option' => 'Cubed']],
        ]);
        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $item->id,
            'employee_name' => 'Robin Santiago',
            'role' => CommissionRole::Assigned,
            'amount' => 20,
        ]);
        PosSalePayment::factory()->create([
            'pos_sale_id' => $sale->id,
            'payment_method_name' => 'GCash',
            'is_cash' => false,
            'amount' => 302,
            'reference_number' => 'GC-1',
        ]);
        PosSaleTax::factory()->create([
            'pos_sale_id' => $sale->id,
            'name' => 'VAT',
            'amount' => 27,
        ]);
        $order = PosOpenOrder::factory()->create([
            'number' => 'OPEN-SHOP',
            'status' => 'placed',
            'phone' => '09171234567',
            'saved_by_name' => 'Ada Reyes',
            'subtotal' => 225,
            'discount' => 100,
            'total' => 275,
            'pos_sale_id' => $sale->id,
            'items' => [[
                'product_name' => 'Aozi Dog',
                'variant_name' => 'Beef',
                'price' => '75.00',
                'quantity' => 3,
                'notes' => 'Preorder',
                'modifiers' => [['name' => 'Cut', 'option' => 'Cubed']],
                'assigned_employee_ids' => [$employee->id],
                'assisted_employee_ids' => [],
            ]],
            'checkout' => [
                'delivery' => [
                    'name' => 'Lalamove',
                    'fee' => '150.00',
                    'instructions' => 'Rider will call.',
                ],
                'payment' => [
                    'name' => 'GCash',
                    'fee' => '0.00',
                    'instructions' => 'Scan the QR at the counter.',
                ],
                'address' => [
                    'line_1' => '12 Mabini',
                    'line_2' => 'Unit 4',
                    'city' => 'San Pedro',
                    'province' => 'Laguna',
                    'latitude' => '14.3595',
                    'longitude' => '121.0473',
                ],
                'voucher' => [
                    'code' => 'WELCOME',
                    'discount' => '100.00',
                ],
            ],
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/orders/'.$order->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Orders/Show')
            ->where('order.number', 'OPEN-SHOP')
            ->where('order.customer', 'Ada Reyes')
            ->where('order.phone', '09171234567')
            ->where('order.delivery', 'Lalamove')
            ->where('order.delivery_instructions', 'Rider will call.')
            ->where('order.address', '12 Mabini, Unit 4, San Pedro, Laguna')
            ->where('order.coordinates', '14.3595, 121.0473')
            ->where('order.payment', 'GCash')
            ->where('order.payment_instructions', 'Scan the QR at the counter.')
            ->where('order.voucher', 'WELCOME')
            ->where('order.lines.0.name', 'Aozi Dog')
            ->where('order.lines.0.variant', 'Beef')
            ->where('order.lines.0.quantity', 3)
            ->where('order.lines.0.line_total_label', '₱225.00')
            ->where('order.lines.0.notes', 'Preorder')
            ->where('order.lines.0.modifiers.0', 'Cut: Cubed')
            ->where('order.lines.0.assigned.0', 'Robin Santiago')
            ->where('order.sale.number', 'POS-1001')
            ->where('order.sale.cashier', 'Hazel Brosas')
            ->where('order.sale.payments.0.name', 'GCash')
            ->where('order.sale.payments.0.reference', 'GC-1')
            ->where('order.sale.taxes.0.name', 'VAT')
            ->where('order.sale.items.0.commissions.0.employee', 'Robin Santiago')
            ->where('order.sale.items.0.commissions.0.amount_label', '₱20.00')
            ->where('order.can_view_receipt', true));
    }

    public function test_preorders_on_open_orders_are_listed_and_exported(): void
    {
        $brand = Brand::factory()->create(['name' => 'Aozi']);
        $category = Category::factory()->create(['name' => 'Dog - Canned']);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create([
                'name' => 'Aozi Dog',
                'brand_id' => $brand->id,
                'category_id' => $category->id,
            ])->id,
            'name' => 'Beef',
            'price' => 75,
        ]);
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $line = [
            'catalog_item_variant_id' => $variant->id,
            'product_name' => 'Aozi Dog',
            'variant_name' => 'Beef',
            'price' => '75.00',
            'quantity' => 3,
            'notes' => 'Preorder',
        ];
        PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'OPEN-PRE',
            'status' => 'placed',
            'items' => [$line, [
                'catalog_item_variant_id' => $variant->id,
                'product_name' => 'Aozi Dog',
                'variant_name' => 'Chicken',
                'price' => '75.00',
                'quantity' => 1,
                'notes' => null,
            ]],
        ]);
        PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'OPEN-DONE',
            'status' => 'completed',
            'items' => [$line],
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/shop/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('preorders', 1)
                ->where('preorders.0.order_number', 'OPEN-PRE')
                ->where('preorders.0.branch', 'Greenheights')
                ->where('preorders.0.brand', 'Aozi')
                ->where('preorders.0.category', 'Dog - Canned')
                ->where('preorders.0.name', 'Aozi Dog')
                ->where('preorders.0.variant', 'Beef')
                ->where('preorders.0.quantity', 3)
                ->where('preorders.0.price_label', '₱75.00'));

        $export = $this->actingAsEmployee()->get('/backoffice/shop/orders/preorders/export');

        $export->assertOk();
        $export->assertDownload('preorders.csv');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('OPEN-PRE', $csv);
        $this->assertStringContainsString('Aozi', $csv);
        $this->assertStringContainsString('Dog - Canned', $csv);
        $this->assertStringContainsString('Beef', $csv);
        $this->assertStringNotContainsString('OPEN-DONE', $csv);
        $this->assertStringNotContainsString('Chicken', $csv);
    }

    public function test_guest_cannot_export_preorders(): void
    {
        $this->get('/backoffice/shop/orders/preorders/export')->assertRedirect(route('login'));
    }
}
