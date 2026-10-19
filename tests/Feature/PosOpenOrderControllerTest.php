<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
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
