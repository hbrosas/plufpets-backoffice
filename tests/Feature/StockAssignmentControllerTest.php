<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_stock_assignments(): void
    {
        $response = $this->get('/backoffice/inventory/stock-assignments');

        $response->assertRedirect(route('login'));
    }

    public function test_received_items_are_listed_for_assignment(): void
    {
        $item = $this->receivedItem(5);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/stock-assignments');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/StockAssignments/Index')
            ->where('items.0.id', $item->id)
            ->where('items.0.remaining', 5));
    }

    public function test_assignment_page_shows_stock_left_at_each_branch(): void
    {
        $north = Branch::factory()->create(['name' => 'North Shop']);
        $south = Branch::factory()->create(['name' => 'South Shop']);
        $item = $this->receivedItem(5, $north);
        BranchInventory::factory()->create([
            'branch_id' => $south->id,
            'catalog_item_variant_id' => $item->catalog_item_variant_id,
            'quantity' => 4,
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/inventory/stock-assignments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branches.0.name', 'North Shop')
                ->where('branches.1.name', 'South Shop')
                ->where('items.0.branch_quantities', [0, 4]));
    }

    public function test_several_products_are_assigned_together_without_changing_price(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $first = $this->receivedItem(8);
        $second = $this->receivedItem(6);
        $firstPrice = $first->variant->price;
        $secondPrice = $second->variant->price;

        $response = $this->actingAsEmployee()->post('/backoffice/inventory/stock-assignments', [
            'branch_id' => $branch->id,
            'items' => [
                ['purchase_order_item_id' => $first->id, 'quantity' => 3],
                ['purchase_order_item_id' => $second->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('inventory.stock-assignments.index'));
        $this->assertDatabaseHas('stock_assignments', [
            'purchase_order_item_id' => $first->id,
            'branch_id' => $branch->id,
            'quantity' => 3,
            'selling_price' => $firstPrice,
        ]);
        $this->assertDatabaseHas('stock_assignments', [
            'purchase_order_item_id' => $second->id,
            'branch_id' => $branch->id,
            'quantity' => 2,
            'selling_price' => $secondPrice,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $first->catalog_item_variant_id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $second->catalog_item_variant_id,
            'quantity' => 2,
        ]);
        $this->assertSame($firstPrice, $first->variant->fresh()->price);
        $this->assertSame($secondPrice, $second->variant->fresh()->price);
        $this->assertDatabaseCount('branch_variant_prices', 0);
    }

    public function test_a_failed_line_assigns_nothing(): void
    {
        $branch = Branch::factory()->create();
        $first = $this->receivedItem(8);
        $second = $this->receivedItem(2);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/stock-assignments')
            ->post('/backoffice/inventory/stock-assignments', [
                'branch_id' => $branch->id,
                'items' => [
                    ['purchase_order_item_id' => $first->id, 'quantity' => 3],
                    ['purchase_order_item_id' => $second->id, 'quantity' => 5],
                ],
            ]);

        $response->assertRedirect('/backoffice/inventory/stock-assignments');
        $response->assertSessionHasErrors(['items.1.quantity']);
        $this->assertDatabaseCount('stock_assignments', 0);
        $this->assertDatabaseCount('branch_inventories', 0);
    }

    public function test_assignment_cannot_exceed_unassigned_quantity(): void
    {
        $branch = Branch::factory()->create();
        $item = $this->receivedItem(2);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/stock-assignments')
            ->post('/backoffice/inventory/stock-assignments', [
                'branch_id' => $branch->id,
                'items' => [[
                    'purchase_order_item_id' => $item->id,
                    'quantity' => 5,
                ]],
            ]);

        $response->assertRedirect('/backoffice/inventory/stock-assignments');
        $response->assertSessionHasErrors(['items.0.quantity']);
        $this->assertDatabaseCount('stock_assignments', 0);
    }

    private function receivedItem(int $received, ?Branch $branch = null): PurchaseOrderItem
    {
        $order = PurchaseOrder::factory()->confirmed()->create($branch === null ? [] : [
            'branch_id' => $branch->id,
        ]);
        $catalogItem = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($catalogItem)->create();

        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $received,
            'received_quantity' => $received,
        ]);
    }
}
