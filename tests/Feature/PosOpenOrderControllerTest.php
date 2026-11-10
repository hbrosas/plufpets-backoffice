<?php

namespace Tests\Feature;

use App\Enums\InventoryHold;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Employee;
use App\Models\HotelStay;
use App\Models\PosOpenOrder;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosOpenOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_save_an_open_order(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/open-orders", [
            'items' => [['catalog_item_variant_id' => 1, 'quantity' => 1]],
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_open_order_is_rejected_without_an_open_shift(): void
    {
        $branch = Branch::factory()->create();
        $variant = CatalogItemVariant::factory()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $this->itemPayload($variant));

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'status' => 'Open a shift before parking an order.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_open_order_is_saved_for_the_branch(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $this->itemPayload($variant, 2));

        $response->assertRedirect(route('pos.index', $branch));
        $this->assertDatabaseHas('pos_open_orders', [
            'branch_id' => $branch->id,
            'name' => 'Table 4',
            'subtotal' => '200.00',
            'saved_by_employee_number' => '10001',
        ]);
    }

    public function test_open_order_requires_a_name(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()->create();
        $payload = $this->itemPayload($variant);
        unset($payload['name']);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $payload);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_saving_again_updates_the_existing_open_order(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Kibble']))
            ->create(['name' => 'Large', 'price' => 100]);
        $order = PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Walk-in',
        ]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->put("/backoffice/pos/register/{$branch->id}/open-orders/{$order->id}", $this->itemPayload($variant, 2, 'Patio'));

        $response->assertRedirect(route('pos.index', $branch));
        $this->assertDatabaseHas('pos_open_orders', [
            'id' => $order->id,
            'name' => 'Patio',
            'subtotal' => '200.00',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 1);
    }

    public function test_open_order_keeps_the_employees_assigned_to_a_service(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->service()->create(['name' => 'Full groom']))
            ->create(['name' => 'Standard', 'price' => 1000]);
        $employee = Employee::factory()->create();
        $employee->profile()->create(['pay_basis' => 'month']);
        $payload = $this->itemPayload($variant);
        $payload['items'][0]['is_product'] = false;
        $payload['items'][0]['assigned_employee_ids'] = [$employee->id];
        $payload['items'][0]['assisted_employee_ids'] = [];

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $payload);

        $response->assertRedirect(route('pos.index', $branch));
        $order = PosOpenOrder::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame([$employee->id], $order->items[0]['assigned_employee_ids']);
        $this->assertSame([], $order->items[0]['assisted_employee_ids']);
    }

    public function test_register_lists_open_orders_for_the_branch(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'OPEN-LOCAL-0001',
            'name' => 'Walk-in',
        ]);
        PosOpenOrder::factory()->create([
            'branch_id' => $other->id,
            'number' => 'OPEN-OTHER-0001',
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/pos/register/{$branch->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('openOrders.0.number', 'OPEN-LOCAL-0001')
            ->where('openOrders.0.name', 'Walk-in')
            ->has('openOrders', 1));
    }

    public function test_guest_cart_is_saved_on_the_checked_in_guest_and_hidden_from_open_orders(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
        ]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Treats']))
            ->create(['name' => 'Default', 'price' => 40]);
        $payload = $this->itemPayload($variant, 2, 'Mina Cruz');
        $payload['hotel_stay_id'] = $stay->id;

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $payload)
            ->assertRedirect(route('pos.index', $branch));

        $payload['items'][0]['quantity'] = 3;
        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $payload)
            ->assertRedirect(route('pos.index', $branch));

        $this->assertDatabaseCount('pos_open_orders', 1);
        $order = PosOpenOrder::query()->firstOrFail();
        $this->assertSame($stay->id, $order->hotel_stay_id);
        $this->assertSame(3, $order->items[0]['quantity']);

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$branch->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('openOrders', 0)
                ->where('hotelStays.0.cart.open_order_id', $order->id)
                ->where('hotelStays.0.cart.items.0.label', 'Treats')
                ->where('hotelStays.0.cart.items.0.quantity', 3)
                ->where('hotelStays.0.cart.subtotal_label', '₱120.00'));
    }

    public function test_guest_cart_is_rejected_after_the_guest_checks_out(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $stay = HotelStay::factory()->checkedOut()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()->create();
        $payload = $this->itemPayload($variant);
        $payload['hotel_stay_id'] = $stay->id;

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/open-orders", $payload)
            ->assertRedirect("/backoffice/pos/register/{$branch->id}")
            ->assertSessionHasErrors([
                'hotel_stay_id' => 'Choose a guest who is still checked in.',
            ]);

        $this->assertDatabaseCount('pos_open_orders', 0);
    }

    public function test_open_order_can_be_removed(): void
    {
        $branch = Branch::factory()->create();
        $order = PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'number' => 'OPEN-LOCAL-0001',
            'name' => 'Table 4',
        ]);

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->delete("/backoffice/pos/register/{$branch->id}/open-orders/{$order->id}")
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('status', 'Open order OPEN-LOCAL-0001 removed.');

        $this->assertDatabaseMissing('pos_open_orders', ['id' => $order->id]);
    }

    public function test_removing_an_open_order_that_is_already_gone_still_succeeds(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->delete("/backoffice/pos/register/{$branch->id}/open-orders/999")
            ->assertRedirect(route('pos.index', $branch))
            ->assertSessionHas('status', 'Open order removed.');
    }

    public function test_removing_an_open_order_releases_held_stock(): void
    {
        $branch = Branch::factory()->create();
        $variant = CatalogItemVariant::factory()->create();
        $order = PosOpenOrder::factory()->create([
            'branch_id' => $branch->id,
            'inventory_hold' => InventoryHold::Held,
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
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved' => 2,
        ]);

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->delete("/backoffice/pos/register/{$branch->id}/open-orders/{$order->id}")
            ->assertRedirect(route('pos.index', $branch));

        $this->assertDatabaseMissing('pos_open_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'reserved' => 0,
        ]);
    }

    public function test_open_order_from_another_branch_cannot_be_removed(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $order = PosOpenOrder::factory()->create(['branch_id' => $other->id]);

        $this->actingAsEmployee()
            ->delete("/backoffice/pos/register/{$branch->id}/open-orders/{$order->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('pos_open_orders', ['id' => $order->id]);
    }

    public function test_open_order_from_another_branch_cannot_be_updated(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()->create();
        $order = PosOpenOrder::factory()->create(['branch_id' => $other->id]);

        $response = $this->actingAsEmployee()
            ->put("/backoffice/pos/register/{$branch->id}/open-orders/{$order->id}", $this->itemPayload($variant));

        $response->assertNotFound();
    }

    /**
     * @return array{name: string, items: list<array<string, mixed>>}
     */
    private function itemPayload(CatalogItemVariant $variant, int $quantity = 1, string $name = 'Table 4'): array
    {
        $variant->loadMissing('catalogItem');

        return [
            'name' => $name,
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'product_name' => $variant->catalogItem?->name ?? $variant->name,
                'variant_name' => $variant->name,
                'price' => number_format((float) $variant->price, 2, '.', ''),
                'quantity' => $quantity,
                'notes' => null,
                'is_product' => true,
                'modifiers' => [],
            ]],
        ];
    }
}
