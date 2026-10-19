<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_inventory(): void
    {
        $response = $this->get('/backoffice/inventory');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_branch_stock_grouped_by_product(): void
    {
        $makati = Branch::factory()->create(['name' => 'Makati Branch']);
        $bgc = Branch::factory()->create(['name' => 'BGC Branch']);
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => 'Default',
            'sku' => 'KIB-001',
            'price' => 120,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $makati->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 12,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $bgc->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 3,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->create()->id,
            'catalog_item_variant_id' => $variant->id,
            'unit_cost' => 80,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->where('rows.0.product', 'Organic kibble')
            ->where('rows.0.sku', 'KIB-001')
            ->where('rows.0.total_quantity', 15)
            ->where('rows.0.locations.0.name', 'BGC Branch')
            ->where('rows.0.locations.0.quantity', 3)
            ->where('rows.0.locations.0.price', '₱120.00')
            ->where('rows.0.locations.1.name', 'Makati Branch')
            ->where('rows.0.locations.1.quantity', 12)
            ->where('rows.0.locations.1.price', '₱120.00')
            ->where('rows.0.purchase_price', '₱80.00')
            ->where('rows.0.purchase_price_amount', 80)
            ->where('rows.0.retail_price', '₱120.00')
            ->where('rows.0.markup', '50%')
            ->where('rows.0.can_move', true)
            ->where('summary.quantity', 15)
            ->where('summary.value', '₱1,200.00'));
    }

    public function test_unassigned_stock_is_shown_on_the_same_product_row(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create(['name' => 'Default', 'price' => 50]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->confirmed()->create()->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 6,
            'received_quantity' => 6,
            'unit_cost' => 30,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->where('rows.0.total_quantity', 10)
            ->where('rows.0.locations.0.name', 'Makati Branch')
            ->where('rows.0.locations.0.quantity', 4)
            ->where('rows.0.locations.0.price', '₱50.00')
            ->where('rows.0.locations.1.name', 'Unassigned')
            ->where('rows.0.locations.1.quantity', 6)
            ->where('rows.0.locations.1.price', '₱50.00')
            ->where('summary.quantity', 10)
            ->where('summary.value', '₱300.00'));
    }

    public function test_guest_is_redirected_from_product_inventory(): void
    {
        $variant = CatalogItemVariant::factory()->create();

        $this->get("/backoffice/inventory/products/{$variant->id}")->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_product_labels(): void
    {
        $variant = CatalogItemVariant::factory()->create();

        $this->get("/backoffice/inventory/products/{$variant->id}/labels")->assertRedirect(route('login'));
    }

    public function test_product_page_shows_purchases_sales_and_stock_history(): void
    {
        $this->travelTo('2026-10-18 08:15:00');

        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create(['name' => 'Default', 'price' => 120]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 8,
        ]);
        $order = PurchaseOrder::factory()->confirmed()->create(['number' => 'PO-0100']);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 8,
            'received_quantity' => 8,
            'unit_cost' => 80,
        ]);
        $receipt = PurchaseOrderReceipt::factory()->create([
            'purchase_order_id' => $order->id,
            'delivery_receipt_number' => 'DR-0100',
            'received_at' => now(),
        ]);
        PurchaseOrderReceiptItem::factory()->create([
            'purchase_order_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'quantity' => 8,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/products/{$variant->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Show')
            ->where('product.name', 'Organic kibble')
            ->where('summary.on_hand', 8)
            ->where('summary.purchased', 8)
            ->where('summary.sold', 0)
            ->where('purchases.0.reference', 'PO-0100')
            ->where('purchases.0.quantity', 8)
            ->where('sales', [])
            ->where('history.0.type', 'Purchase')
            ->where('history.0.quantity', 8));
    }

    public function test_labels_include_barcode_price_name_variant_and_repack_dates(): void
    {
        $this->travelTo('2026-10-18 08:15:00');

        $item = CatalogItem::factory()->create(['name' => "Kibble <script>alert('xss')</script>"]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'sku' => 'KIB-1KG',
            'barcode' => '123456789012',
            'price' => 257.14,
        ]);
        StockConversion::factory()->create([
            'from_catalog_item_variant_id' => CatalogItemVariant::factory(),
            'to_catalog_item_variant_id' => $variant->id,
            'from_quantity' => 1,
            'to_quantity' => 7,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/products/{$variant->id}/labels?copies=2");

        $response->assertOk();
        $response->assertSee('1kg', false);
        $response->assertSee('₱257.14', false);
        $response->assertSee('123456789012', false);
        $response->assertSee('Supply Date: Oct 18, 2026', false);
        $response->assertSee('<svg', false);
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
        $this->assertSame(2, substr_count($response->getContent(), 'class="label"'));
    }
}
