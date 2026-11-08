<?php

namespace Tests\Feature;

use App\Enums\InventoryHold;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchVariantPrice;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\DeliveryMethod;
use App\Models\DeliverySchedule;
use App\Models\PaymentMethod;
use App\Models\PosOpenOrder;
use App\Models\Voucher;
use App\Support\SmsDisclaimer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.semaphore.key' => null]);
    }

    public function test_home_shows_the_selected_branch_catalog(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro', 'is_active' => true]);
        $food = Category::factory()->create(['name' => 'Food']);
        $grooming = Category::factory()->create(['name' => 'Grooming']);

        $kibble = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'category_id' => $food->id,
                'has_branch_pricing' => true,
            ]))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $greenheights->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 2,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $sanPedro->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 9,
        ]);
        BranchVariantPrice::factory()->create([
            'branch_id' => $greenheights->id,
            'catalog_item_variant_id' => $kibble->id,
            'price' => 80,
        ]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->service()->create([
                'name' => 'Bath',
                'category_id' => $grooming->id,
            ]))
            ->create(['name' => 'Default', 'price' => 250]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Treats',
                'category_id' => $food->id,
            ]))
            ->create(['name' => 'Default', 'price' => 40]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->inactive()->create(['name' => 'Hidden']))
            ->create(['name' => 'Default']);

        $this->get('/?branch='.$greenheights->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('filters.branch', $greenheights->id)
                ->where('products.0.name', 'Bath')
                ->where('products.0.item_name', 'Bath')
                ->where('products.0.variant_name', null)
                ->where('products.0.price_label', '₱250.00')
                ->where('products.0.stock_label', 'In Stock')
                ->where('products.1.name', 'Kibble · Large')
                ->where('products.1.item_name', 'Kibble')
                ->where('products.1.variant_name', 'Large')
                ->where('products.1.price_label', '₱80.00')
                ->where('products.1.stock_label', 'In Stock 2')
                ->where('products.2.name', 'Treats')
                ->where('products.2.stock_label', 'Out of Stock')
                ->where('products.2.in_stock', false)
                ->where('products.2.allows_preorder', true)
                ->has('products', 3)
                ->has('categories', 2));

        $this->get('/?branch='.$sanPedro->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.1.price_label', '₱100.00')
                ->where('products.1.stock_label', 'In Stock 9'));

        $this->get('/?branch='.$greenheights->id.'&category='.$food->id.'&q=Kibble')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 3)
                ->where('products.1.name', 'Kibble · Large')
                ->where('products.1.category_name', 'Food'));
    }

    public function test_checkout_uses_the_instruction_for_the_selected_branch(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro', 'is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);
        $delivery = DeliveryMethod::factory()->create([
            'name' => 'Lalamove',
            'notes' => 'Same rider for every branch.',
            'instructions_for_all_branches' => true,
            'is_active' => true,
        ]);
        $payment = PaymentMethod::factory()->create([
            'name' => 'GCash',
            'instructions' => 'Shared QR',
            'instructions_for_all_branches' => false,
            'is_active' => true,
        ]);
        $payment->branchInstructions()->create([
            'branch_id' => $greenheights->id,
            'instructions' => 'Scan the Greenheights QR.',
        ]);
        $payment->branchInstructions()->create([
            'branch_id' => $sanPedro->id,
            'instructions' => 'Scan the San Pedro QR.',
        ]);

        $this->get('/checkout?branch='.$greenheights->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentMethods.0.instructions', 'Scan the Greenheights QR.')
                ->where('deliveryMethods.0.instructions', 'Same rider for every branch.'));

        $this->postJson('/checkout/quote', [
            'branch_id' => $sanPedro->id,
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ])->assertOk()
            ->assertJsonPath('payment_instructions', 'Scan the San Pedro QR.')
            ->assertJsonPath('delivery_instructions', 'Same rider for every branch.');
    }

    public function test_hidden_categories_stay_off_the_public_shop(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $hidden = Category::factory()->create(['name' => 'Supplies', 'show_on_shop' => false]);
        $shown = Category::factory()->create(['name' => 'Food', 'show_on_shop' => true]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Leash',
                'category_id' => $hidden->id,
            ]))
            ->create(['name' => 'Default', 'price' => 90]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'category_id' => $shown->id,
            ]))
            ->create(['name' => 'Default', 'price' => 100]);

        $this->get('/?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Food')
                ->has('products', 1)
                ->where('products.0.name', 'Kibble'));
    }

    public function test_guest_checkout_saves_the_branch_price_without_a_sale(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'has_branch_pricing' => true,
            ]))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        BranchVariantPrice::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'price' => 80,
        ]);

        $response = $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2, 'price' => 1],
            ],
        ] + $this->checkoutFields());

        $response->assertRedirect(route('shop.track', ['number' => PosOpenOrder::query()->value('number')]));
        $response->assertSessionHas('shop_order_id');

        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('Ada Reyes · 09171234567', $order->name);
        $this->assertSame('160.00', $order->subtotal);
        $this->assertSame('Ada Reyes', $order->saved_by_name);
        $this->assertSame('', $order->saved_by_employee_number);
        $this->assertSame('80.00', $order->items[0]['price']);
        $this->assertSame(2, $order->items[0]['quantity']);
        $this->assertSame([], $order->items[0]['modifiers']);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
            'reserved' => 2,
        ]);
        $this->assertSame(InventoryHold::Held, $order->inventory_hold);
    }

    public function test_checkout_rejects_more_than_the_branch_has_in_stock(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Treats']))
            ->create(['name' => 'Default', 'price' => 40]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 3],
            ],
        ] + $this->checkoutFields());

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'items' => 'Only 1 left for Treats.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    public function test_out_of_stock_item_can_be_preordered(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Treats']))
            ->create(['name' => 'Default', 'price' => 40]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
        ]);

        $response = $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ] + $this->checkoutFields());

        $response->assertRedirect(route('shop.track', ['number' => PosOpenOrder::query()->value('number')]));
        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('80.00', $order->subtotal);
        $this->assertSame('Preorder', $order->items[0]['notes']);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
            'reserved' => 0,
        ]);
        $this->assertNull($order->inventory_hold);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_shop_hides_preorder_when_an_out_of_stock_product_does_not_allow_it(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Treats',
                'allows_preorder' => false,
            ]))
            ->create(['name' => 'Default', 'price' => 40]);

        $this->get('/?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('products.0.name', 'Treats')
                ->where('products.0.in_stock', false)
                ->where('products.0.allows_preorder', false));
    }

    public function test_out_of_stock_item_cannot_be_preordered_when_preorder_is_closed(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Treats',
                'allows_preorder' => false,
            ]))
            ->create(['name' => 'Default', 'price' => 40]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
        ]);

        $response = $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ] + $this->checkoutFields());

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'items' => 'Treats is out of stock.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
        ]);
    }

    public function test_a_placed_order_holds_stock_until_the_order_is_cancelled(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Treats',
                'allows_preorder' => false,
            ]))
            ->create(['name' => 'Default', 'price' => 40]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ] + $this->checkoutFields())->assertRedirect();

        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(InventoryHold::Held, $order->inventory_hold);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved' => 2,
        ]);

        $this->get('/?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.stock', 0)
                ->where('products.0.in_stock', false));

        $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Bea Santos',
            'phone' => '09181234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ] + $this->checkoutFields())
            ->assertRedirect('/')
            ->assertSessionHasErrors(['items' => 'Treats is out of stock.']);

        $this->assertDatabaseCount('pos_open_orders', 1);

        $this->actingAsEmployee()->putJson("/backoffice/pos/register/{$branch->id}/online-orders/{$order->id}", [
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertSame(InventoryHold::Released, $order->fresh()->inventory_hold);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved' => 0,
        ]);

        $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Bea Santos',
            'phone' => '09181234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ] + $this->checkoutFields())->assertRedirect();

        $this->assertDatabaseCount('pos_open_orders', 2);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved' => 2,
        ]);
    }

    public function test_a_service_order_does_not_hold_stock(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->service()->create(['name' => 'Bath']))
            ->create(['name' => 'Default', 'price' => 250]);

        $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ] + $this->checkoutFields())->assertRedirect();

        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertNull($order->inventory_hold);
        $this->assertDatabaseCount('branch_inventories', 0);
    }

    public function test_checkout_requires_a_name_and_phone(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);

        $response = $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => '',
            'phone' => '',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'name' => 'Enter your name.',
            'phone' => 'Enter your contact number.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_contact_number_must_be_a_mobile_number(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);

        $response = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '12345',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ] + $this->checkoutFields());

        $response->assertRedirect('/checkout');
        $response->assertSessionHasErrors([
            'phone' => 'Enter a mobile number like 09171234567.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_delivery_address_is_required_when_the_method_asks_for_it(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);
        $delivery = DeliveryMethod::factory()->create([
            'fee' => 20,
            'asks_for_address' => true,
            'notes' => 'Leave it with the guard.',
        ]);
        $payment = PaymentMethod::factory()->create(['fee' => 0, 'is_cash' => true]);

        $response = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '+639171234567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect('/checkout');
        $response->assertSessionHasErrors(['address_line_1', 'city', 'province']);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_checkout_asks_for_change_when_the_payment_method_requires_it(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItem::factory()->service()->create()
            ->variants()->create([
                'name' => 'Standard',
                'price' => 80,
                'is_active' => true,
            ]);
        $delivery = DeliveryMethod::factory()->create(['fee' => 20, 'asks_for_address' => false]);
        $payment = PaymentMethod::factory()->asksForChange()->create(['name' => 'Cash', 'fee' => 0, 'is_cash' => true]);

        $missing = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $missing->assertRedirect('/checkout');
        $missing->assertSessionHasErrors(['change_for' => 'Enter the amount to change for.']);
        $this->assertDatabaseCount('pos_open_orders', 0);

        $short = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'change_for' => '50.00',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $short->assertRedirect('/checkout');
        $short->assertSessionHasErrors(['change_for' => 'Change for must cover the total.']);

        $response = $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'change_for' => '150.00',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('100.00', $order->total);
        $this->assertSame('150.00', $order->checkout['payment']['change_for']);
        $this->assertSame('50.00', $order->checkout['payment']['change_due']);
    }

    public function test_checkout_adds_fees_and_a_voucher_then_texts_the_customer(): void
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
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $delivery = DeliveryMethod::factory()->create([
            'name' => 'Lalamove',
            'fee' => 50,
            'asks_for_address' => true,
            'notes' => 'Rider will call.',
        ]);
        $payment = PaymentMethod::factory()->create([
            'name' => 'GCash',
            'fee' => 10,
            'instructions' => 'Send to 09170000000.',
        ]);
        Voucher::factory()->percent(10)->create(['code' => 'SAVE10']);

        $response = $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '0917 123 4567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'voucher_code' => 'save10',
            'address_line_1' => '12 Mabini',
            'address_line_2' => 'Unit 4',
            'city' => 'San Pedro',
            'province' => 'Laguna',
            'latitude' => '14.358600',
            'longitude' => '121.056900',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('shop.track', ['number' => PosOpenOrder::query()->value('number')]));
        $response->assertSessionHas('shop_text_notice', 'We\'ll text 09171234567 when the status changes.');

        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('09171234567', $order->phone);
        $this->assertSame('placed', $order->status);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('20.00', $order->discount);
        $this->assertSame('240.00', $order->total);
        $this->assertSame('12 Mabini', $order->checkout['address']['line_1']);
        $this->assertArrayNotHasKey('latitude', $order->checkout['address']);
        $this->assertArrayNotHasKey('longitude', $order->checkout['address']);
        $this->assertSame('SAVE10', $order->checkout['voucher']['code']);
        $this->assertDatabaseHas('sms_messages', [
            'branch_id' => $branch->id,
            'number' => '09171234567',
            'body' => SmsDisclaimer::append('Hi Ada Reyes, we received order '.$order->number.' for ₱240.00 at Greenheights.'),
        ]);
        $this->assertDatabaseCount('pos_sales', 0);

        $track = $this->get(route('shop.track'));

        $track->assertOk();
        $track->assertInertia(fn (Assert $page) => $page
            ->component('Site/Track')
            ->where('placed', true)
            ->where('textNotice', 'We\'ll text 09171234567 when the status changes.')
            ->where('number', $order->number)
            ->where('order.number', $order->number)
            ->where('order.status', 'placed')
            ->where('order.status_label', 'Order Placed')
            ->where('order.delivery.name', 'Lalamove')
            ->where('order.delivery.instructions', 'Rider will call.')
            ->where('order.delivery.fee_label', '₱50.00')
            ->where('order.payment.name', 'GCash')
            ->where('order.payment.instructions', 'Send to 09170000000.')
            ->where('order.address', '12 Mabini, Unit 4, San Pedro, Laguna')
            ->where('order.voucher', 'SAVE10')
            ->where('order.total_label', '₱240.00')
            ->where('order.items.0.name', 'Kibble · Large')
            ->where('order.items.0.quantity', 2));

        $order->update(['status' => 'out_for_delivery']);

        $this->get(route('shop.track'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status', 'out_for_delivery')
                ->where('order.status_label', 'Out for Delivery')
                ->where('placed', false));

        $this->flushSession();

        $this->get(route('shop.track'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Track')
                ->where('order', null));
    }

    public function test_each_shop_order_gets_a_short_unique_number(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);
        $payload = [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ] + $this->checkoutFields();

        $first = $this->post('/checkout', $payload);
        $second = $this->post('/checkout', $payload);
        $numbers = PosOpenOrder::query()->orderBy('id')->pluck('number');

        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);

        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^(?=.*[A-Z])(?=.*\d)[A-HJ-NP-Z2-9]{6}$/', $number);
        }

        $first->assertRedirect(route('shop.track', ['number' => $numbers[0]]));
        $second->assertRedirect(route('shop.track', ['number' => $numbers[1]]));
    }

    public function test_track_finds_an_order_from_the_order_number(): void
    {
        PosOpenOrder::factory()->create([
            'number' => 'K7M2P4',
            'status' => 'preparing',
        ]);
        PosOpenOrder::factory()->create([
            'number' => 'K7M2P4',
            'status' => 'placed',
        ]);
        PosOpenOrder::factory()->create([
            'number' => 'PARKED1',
            'status' => null,
        ]);

        $this->get(route('shop.track', ['number' => 'k7 m2p4']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Track')
                ->where('missing', false)
                ->where('number', 'K7M2P4')
                ->where('order.number', 'K7M2P4')
                ->where('order.status', 'placed'));

        $this->get(route('shop.track', ['number' => 'PARKED1']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('missing', true)
                ->where('order', null));

        $this->get(route('shop.track', ['number' => 'NOPE12']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('missing', true)
                ->where('order', null));
    }

    public function test_track_page_is_empty_without_a_current_order(): void
    {
        $response = $this->get(route('shop.track'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Site/Track')
            ->where('order', null)
            ->where('number', '')
            ->where('missing', false)
            ->where('placed', false));
    }

    public function test_apply_accepts_an_active_voucher(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        Voucher::factory()->create(['code' => 'SAVE10']);

        $response = $this->postJson('/checkout/voucher', [
            'branch_id' => $branch->id,
            'voucher_code' => 'save10',
        ]);

        $response->assertOk();
        $response->assertJsonPath('code', 'SAVE10');
    }

    public function test_apply_rejects_an_unknown_voucher(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $response = $this->postJson('/checkout/voucher', [
            'branch_id' => $branch->id,
            'voucher_code' => 'NOPE',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'voucher_code' => 'That voucher is not valid.',
        ]);
    }

    public function test_apply_rejects_an_inactive_voucher(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        Voucher::factory()->inactive()->create(['code' => 'OLD10']);

        $response = $this->postJson('/checkout/voucher', [
            'branch_id' => $branch->id,
            'voucher_code' => 'OLD10',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'voucher_code' => 'That voucher is not valid.',
        ]);
    }

    public function test_apply_requires_a_voucher_code(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $response = $this->postJson('/checkout/voucher', [
            'branch_id' => $branch->id,
            'voucher_code' => '   ',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'voucher_code' => 'Enter a voucher code.',
        ]);
    }

    public function test_instruction_photos_show_on_checkout(): void
    {
        Storage::fake('public');
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);

        $this->actingAsEmployee()->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'instructions' => 'Scan this QR.',
            'is_cash' => false,
            'is_active' => true,
            'images' => [UploadedFile::fake()->image('qr.jpg')],
        ])->assertRedirect();

        $this->actingAsEmployee()->post('/backoffice/shop/delivery-methods', [
            'name' => 'Lalamove',
            'fee' => '50',
            'notes' => 'Show this to the rider.',
            'is_active' => true,
            'images' => [UploadedFile::fake()->image('map.jpg')],
        ])->assertRedirect();

        $payment = PaymentMethod::query()->where('name', 'GCash')->first();
        $delivery = DeliveryMethod::query()->where('name', 'Lalamove')->first();

        $this->get('/checkout?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentMethods.0.instructions', 'Scan this QR.')
                ->where('paymentMethods.0.instruction_images.0', $payment->instructionImageUrls($branch->id)[0])
                ->where('deliveryMethods.0.instructions', 'Show this to the rider.')
                ->where('deliveryMethods.0.instruction_images.0', $delivery->instructionImageUrls($branch->id)[0]));
    }

    public function test_checkout_lists_open_slots_for_the_branch(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00:00', 'UTC'));
        $branch = Branch::factory()->create(['is_active' => true]);
        $open = DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-11-09',
            'starts_at' => '09:00:00',
        ]);
        DeliverySchedule::factory()->ended()->create(['branch_id' => $branch->id]);
        DeliverySchedule::factory()->create();

        $this->get('/checkout?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliverySchedules', [
                    [
                        'id' => $open->id,
                        'on' => '2026-11-09',
                        'date' => 'Nov 9, 2026',
                        'time' => '9:00 AM',
                        'day' => 'Monday',
                    ],
                ]));
    }

    public function test_checkout_lists_the_next_six_dates_for_a_weekly_slot(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00:00', 'UTC'));
        $branch = Branch::factory()->create(['is_active' => true]);
        $monday = DeliverySchedule::factory()->weekly([1])->create([
            'branch_id' => $branch->id,
            'starts_at' => '10:00:00',
        ]);

        $this->get('/checkout?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliverySchedules', [
                    ['id' => $monday->id, 'on' => '2026-11-09', 'date' => 'Nov 9, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                    ['id' => $monday->id, 'on' => '2026-11-16', 'date' => 'Nov 16, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                    ['id' => $monday->id, 'on' => '2026-11-23', 'date' => 'Nov 23, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                    ['id' => $monday->id, 'on' => '2026-11-30', 'date' => 'Nov 30, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                    ['id' => $monday->id, 'on' => '2026-12-07', 'date' => 'Dec 7, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                    ['id' => $monday->id, 'on' => '2026-12-14', 'date' => 'Dec 14, 2026', 'time' => '10:00 AM', 'day' => 'Monday'],
                ]));
    }

    public function test_checkout_lists_only_the_next_six_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00:00', 'UTC'));
        $branch = Branch::factory()->create(['is_active' => true]);
        $today = DeliverySchedule::factory()->daily()->create([
            'branch_id' => $branch->id,
            'starts_at' => '15:00:00',
        ]);
        $tomorrowMorning = DeliverySchedule::factory()->daily()->create([
            'branch_id' => $branch->id,
            'starts_at' => '08:00:00',
        ]);
        $tomorrow = DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-11-09',
            'starts_at' => '09:00:00',
        ]);
        $wednesday = DeliverySchedule::factory()->weekly([3])->create([
            'branch_id' => $branch->id,
            'starts_at' => '16:00:00',
        ]);
        $thursday = DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-11-12',
            'starts_at' => '09:00:00',
        ]);
        $friday = DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-11-13',
            'starts_at' => '09:00:00',
        ]);
        DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => '2026-11-14',
            'starts_at' => '09:00:00',
        ]);

        $this->get('/checkout?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliverySchedules', [
                    ['id' => $today->id, 'on' => '2026-11-08', 'date' => 'Nov 8, 2026', 'time' => '3:00 PM', 'day' => 'Sunday'],
                    ['id' => $tomorrowMorning->id, 'on' => '2026-11-09', 'date' => 'Nov 9, 2026', 'time' => '8:00 AM', 'day' => 'Monday'],
                    ['id' => $tomorrow->id, 'on' => '2026-11-09', 'date' => 'Nov 9, 2026', 'time' => '9:00 AM', 'day' => 'Monday'],
                    ['id' => $today->id, 'on' => '2026-11-09', 'date' => 'Nov 9, 2026', 'time' => '3:00 PM', 'day' => 'Monday'],
                    ['id' => $tomorrowMorning->id, 'on' => '2026-11-10', 'date' => 'Nov 10, 2026', 'time' => '8:00 AM', 'day' => 'Tuesday'],
                    ['id' => $today->id, 'on' => '2026-11-10', 'date' => 'Nov 10, 2026', 'time' => '3:00 PM', 'day' => 'Tuesday'],
                ]));
    }

    public function test_a_preferred_slot_is_required_when_the_method_uses_a_delivery_schedule(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->service()->create()->id,
            'price' => 50,
        ]);
        $delivery = DeliveryMethod::factory()->usesSchedule()->create(['fee' => 0]);
        $payment = PaymentMethod::factory()->create(['fee' => 0, 'is_cash' => true]);
        $other = DeliverySchedule::factory()->create([
            'scheduled_on' => now('Asia/Manila')->addDay()->toDateString(),
        ]);

        $missing = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $missing->assertRedirect('/checkout');
        $missing->assertSessionHasErrors([
            'delivery_schedule_id' => 'Choose a preferred slot.',
        ]);

        $wrongBranch = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'delivery_schedule_id' => $other->id,
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $wrongBranch->assertRedirect('/checkout');
        $wrongBranch->assertSessionHasErrors([
            'delivery_schedule_id' => 'Choose a preferred slot.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_checkout_saves_the_preferred_slot(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->service()->create()->id,
            'price' => 50,
        ]);
        $delivery = DeliveryMethod::factory()->usesSchedule()->create(['fee' => 0]);
        $payment = PaymentMethod::factory()->create(['fee' => 0, 'is_cash' => true]);
        $schedule = DeliverySchedule::factory()->create([
            'branch_id' => $branch->id,
            'scheduled_on' => now('Asia/Manila')->addDay()->toDateString(),
            'starts_at' => '09:00:00',
        ]);

        $response = $this->from('/checkout')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'delivery_method_id' => $delivery->id,
            'delivery_schedule_id' => $schedule->id,
            'preferred_on' => $schedule->scheduled_on->toDateString(),
            'payment_method_id' => $payment->id,
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pos_open_orders', [
            'branch_id' => $branch->id,
            'delivery_schedule_id' => $schedule->id,
            'phone' => '09171234567',
        ]);
    }

    /**
     * @return array{delivery_method_id: int, payment_method_id: int}
     */
    private function checkoutFields(): array
    {
        $delivery = DeliveryMethod::factory()->create(['fee' => 0, 'asks_for_address' => false]);
        $payment = PaymentMethod::factory()->create(['fee' => 0, 'is_cash' => true]);

        return [
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
        ];
    }
}
