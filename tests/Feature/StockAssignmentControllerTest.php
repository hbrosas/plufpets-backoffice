<?php

namespace Tests\Feature;

use App\Models\Branch;
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

    public function test_stock_is_assigned_to_a_branch_with_a_selling_price(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $item = $this->receivedItem(8);

        $response = $this->actingAsEmployee()->post('/backoffice/inventory/stock-assignments', [
            'purchase_order_item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 3,
            'selling_price' => 199.5,
        ]);

        $response->assertRedirect(route('inventory.stock-assignments.index'));
        $this->assertDatabaseHas('stock_assignments', [
            'purchase_order_item_id' => $item->id,
            'branch_id' => $branch->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $item->catalog_item_variant_id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('branch_variant_prices', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $item->catalog_item_variant_id,
            'price' => 199.5,
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'id' => $item->catalog_item_variant_id,
            'price' => 199.5,
        ]);
    }

    public function test_assignment_cannot_exceed_unassigned_quantity(): void
    {
        $branch = Branch::factory()->create();
        $item = $this->receivedItem(2);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/stock-assignments')
            ->post('/backoffice/inventory/stock-assignments', [
                'purchase_order_item_id' => $item->id,
                'branch_id' => $branch->id,
                'quantity' => 5,
                'selling_price' => 50,
            ]);

        $response->assertRedirect('/backoffice/inventory/stock-assignments');
        $response->assertSessionHasErrors(['quantity']);
        $this->assertDatabaseCount('stock_assignments', 0);
    }

    private function receivedItem(int $received): PurchaseOrderItem
    {
        $order = PurchaseOrder::factory()->confirmed()->create();
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
