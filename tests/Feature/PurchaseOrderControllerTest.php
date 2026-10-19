<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_purchase_orders(): void
    {
        $response = $this->get('/backoffice/inventory/purchase-orders');

        $response->assertRedirect(route('login'));
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/inventory/purchase-orders', []);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $response->assertSessionHasErrors([
            'supplier_id' => 'Choose a supplier.',
            'branch_id' => 'Choose the branch for this purchase order.',
            'items' => 'Add at least one product.',
        ]);
    }

    public function test_purchase_order_is_created_as_a_draft(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Acme Feeds']);
        $branch = Branch::factory()->create([
            'name' => 'Makati Branch',
            'registered_business_name' => 'Pluf Pets Trading',
            'tin' => '123-456-789-00000',
        ]);
        $variant = $this->productVariant();

        $response = $this->actingAsEmployee()->post('/backoffice/inventory/purchase-orders', [
            'supplier_id' => $supplier->id,
            'branch_id' => $branch->id,
            'notes' => 'First order',
            'items' => [[
                'catalog_item_variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 85.5,
            ]],
        ]);

        $order = PurchaseOrder::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect(route('inventory.purchase-orders.show', $order));
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame('PO-0001', $order->number);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'quantity' => 10,
        ]);
    }

    public function test_service_variants_are_rejected(): void
    {
        $supplier = Supplier::factory()->create();
        $item = CatalogItem::factory()->service()->create();
        $variant = CatalogItemVariant::factory()->for($item)->create();

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/inventory/purchase-orders', [
                'supplier_id' => $supplier->id,
                'branch_id' => Branch::factory()->create()->id,
                'items' => [[
                    'catalog_item_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_cost' => 10,
                ]],
            ]);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $response->assertSessionHasErrors(['items.0.catalog_item_variant_id']);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_draft_purchase_order_can_be_confirmed(): void
    {
        $order = PurchaseOrder::factory()->create();
        $variant = $this->productVariant();
        $order->items()->create([
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
            'unit_cost' => 20,
        ]);

        $response = $this->actingAsEmployee()->post("/backoffice/inventory/purchase-orders/{$order->id}/confirm");

        $response->assertRedirect(route('inventory.purchase-orders.show', $order));
        $this->assertSame(PurchaseOrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_print_page_shows_the_purchase_order(): void
    {
        $branch = Branch::factory()->create([
            'registered_business_name' => "O'Reilly <script>alert('xss')</script>",
            'address' => 'Ayala Avenue',
            'tin' => '123-456-789-00000',
        ]);
        $order = PurchaseOrder::factory()->create([
            'number' => 'PO-0099',
            'branch_id' => $branch->id,
        ]);
        $variant = $this->productVariant();
        $order->items()->create([
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_cost' => 15,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/purchase-orders/{$order->id}/print");

        $response->assertOk();
        $response->assertSee('PO-0099', false);
        $response->assertSee('Print', false);
        $response->assertSee('Supplier', false);
        $response->assertSee('Business information', false);
        $response->assertSee('Ayala Avenue', false);
        $response->assertSee('TIN 123-456-789-00000', false);
        $response->assertSee('₱30.00', false);
        $response->assertSee('Total quantity', false);
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_purchase_order_file_can_be_downloaded(): void
    {
        $order = PurchaseOrder::factory()->create(['number' => 'PO-0099']);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/purchase-orders/{$order->id}/print?download=1");

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename="PO-0099.html"');
    }

    public function test_create_form_lists_categories_brands_and_branches(): void
    {
        Category::factory()->create(['name' => 'Food']);
        Brand::factory()->create(['name' => 'Pluf Pets']);
        Branch::factory()->create(['name' => 'Makati Branch']);
        Supplier::factory()->create([
            'name' => 'Acme Feeds',
            'contact_name' => 'Jane Cruz',
            'phone' => '09171234567',
            'address' => 'Pasig Boulevard',
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/purchase-orders/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/PurchaseOrders/Create')
            ->where('categories', ['Food'])
            ->where('brands', ['Pluf Pets'])
            ->where('branches.0.name', 'Makati Branch')
            ->where('suppliers.0.name', 'Acme Feeds')
            ->where('suppliers.0.contact_name', 'Jane Cruz')
            ->where('suppliers.0.address', 'Pasig Boulevard'));
    }

    public function test_show_page_lists_business_information_and_peso_totals(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Makati Branch',
            'registered_business_name' => 'Pluf Pets Trading',
            'tin' => '123-456-789-00000',
            'address' => 'Ayala Avenue',
        ]);
        $order = PurchaseOrder::factory()->create(['branch_id' => $branch->id]);
        $variant = $this->productVariant();
        $order->items()->create([
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 10,
            'unit_cost' => 85.5,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/purchase-orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/PurchaseOrders/Show')
            ->where('purchaseOrder.total', '₱855.00')
            ->where('purchaseOrder.ordered_quantity', 10)
            ->where('purchaseOrder.business.registered_business_name', 'Pluf Pets Trading')
            ->where('purchaseOrder.business.tin', '123-456-789-00000')
            ->where('purchaseOrder.receipts', []));
    }

    public function test_missing_product_can_be_created_from_the_purchase_order_page(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        $brand = Brand::factory()->create(['name' => 'Pluf Pets']);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/inventory/purchase-orders/products', [
                'name' => 'Dental chews',
                'category_name' => 'Food',
                'brand_name' => 'Pluf Pets',
                'sku' => 'CHEW-1',
                'selling_price' => 120,
                'unit_cost' => 60,
            ]);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $response->assertSessionHas('status', 'Product added to the catalog.');
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Dental chews',
            'type' => CatalogItemType::Product->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'sku' => 'CHEW-1',
            'price' => 120,
        ]);
    }

    public function test_product_from_purchase_order_creates_missing_category_and_brand(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/inventory/purchase-orders/products', [
                'name' => 'House treats',
                'category_name' => 'Treats',
                'brand_name' => 'House Brand',
                'sku' => 'TREAT-1',
                'selling_price' => 80,
                'unit_cost' => 40,
            ]);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $this->assertDatabaseHas('categories', ['name' => 'Treats']);
        $this->assertDatabaseHas('brands', ['name' => 'House Brand']);
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'House treats',
            'category_id' => Category::query()->where('name', 'Treats')->value('id'),
            'brand_id' => Brand::query()->where('name', 'House Brand')->value('id'),
        ]);
    }

    public function test_index_lists_purchase_orders(): void
    {
        PurchaseOrder::factory()->create(['number' => 'PO-0042']);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/purchase-orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/PurchaseOrders/Index')
            ->where('purchaseOrders.data.0.number', 'PO-0042'));
    }

    private function productVariant(): CatalogItemVariant
    {
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);

        return CatalogItemVariant::factory()->for($item)->create(['name' => 'Default']);
    }
}
