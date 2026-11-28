<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockConversion;
use App\Support\XlsxSheet;
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

    public function test_inventory_keeps_stock_for_the_selected_category(): void
    {
        $food = Category::factory()->create(['name' => 'Food']);
        $treats = Category::factory()->create(['name' => 'Treats']);
        $branch = Branch::factory()->create();
        $this->stock($branch, 'Kibble', $food, null, 8);
        $this->unassignedStock('Biscuit', $treats, null, 3);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory?category=Food');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.product', 'Kibble')
            ->where('summary.quantity', 8)
            ->where('filters.category', 'Food')
            ->where('categories', ['Food', 'Treats']));
    }

    public function test_inventory_keeps_stock_for_the_selected_brand(): void
    {
        $royal = Brand::factory()->create(['name' => 'Royal']);
        $aozi = Brand::factory()->create(['name' => 'Aozi']);
        $branch = Branch::factory()->create();
        $this->stock($branch, 'Kibble', null, $royal, 5);
        $this->stock($branch, 'Cans', null, $aozi, 2);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory?brand=Aozi');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.product', 'Cans')
            ->where('summary.quantity', 2)
            ->where('filters.brand', 'Aozi')
            ->where('brands', ['Aozi', 'Royal']));
    }

    public function test_inventory_can_show_stock_with_no_category(): void
    {
        $food = Category::factory()->create(['name' => 'Food']);
        $branch = Branch::factory()->create();
        $this->stock($branch, 'Kibble', $food, null, 4);
        $this->stock($branch, 'Loose treats', null, null, 6);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory?category=__none');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.product', 'Loose treats')
            ->where('summary.quantity', 6));
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

    public function test_a_sale_and_refund_change_the_product_inventory_count(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product, 'name' => 'Organic kibble']))
            ->create(['name' => 'Default', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        $payment = PaymentMethod::factory()->cash()->create();

        $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/sales", [
                'items' => [
                    ['catalog_item_variant_id' => $variant->id, 'quantity' => 2],
                ],
                'payments' => [[
                    'payment_method_id' => $payment->id,
                    'amount' => '200',
                    'amount_tendered' => '200',
                ]],
            ])
            ->assertRedirect(route('pos.index', $branch));

        $this->actingAsEmployee()
            ->get("/backoffice/inventory/products/{$variant->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.on_hand', 3)
                ->where('summary.sold', 2)
                ->where('locations.0.quantity', 3)
                ->where('sales.0.quantity', 2)
                ->where('sales.0.refunded', false)
                ->where('history', fn (mixed $history): bool => collect($history)->contains(
                    fn (array $row): bool => $row['type'] === 'Sale' && $row['quantity'] === -2,
                )));

        $sale = PosSale::query()->firstOrFail();

        $this->actingAsEmployee()
            ->from('/backoffice/sales/receipts/'.$sale->id)
            ->post('/backoffice/sales/receipts/'.$sale->id.'/refund', [
                'reason' => 'Customer returned the item',
            ])
            ->assertRedirect('/backoffice/sales/receipts/'.$sale->id);

        $this->actingAsEmployee()
            ->get("/backoffice/inventory/products/{$variant->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.on_hand', 5)
                ->where('summary.sold', 0)
                ->where('locations.0.quantity', 5)
                ->where('sales.0.refunded', true)
                ->where('history', fn (mixed $history): bool => collect($history)->contains(
                    fn (array $row): bool => $row['type'] === 'Refund' && $row['quantity'] === 2,
                )));
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

    public function test_guest_is_redirected_from_the_inventory_export(): void
    {
        $this->get('/backoffice/inventory/export?format=pdf')->assertRedirect(route('login'));
    }

    public function test_inventory_exports_a_compact_pdf(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $this->stock($branch, 'Organic kibble', null, null, 12);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/export?format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inventory-'.now()->toDateString().'.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_inventory_exports_the_filtered_stock_workbook(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $food = Category::factory()->create(['name' => 'Food']);
        $toys = Category::factory()->create(['name' => 'Toys']);
        $this->stock($branch, 'Organic kibble', $food, null, 12);
        $this->stock($branch, 'Rope toy', $toys, null, 4);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/export?format=xlsx&category=Food');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'inventory-xlsx');
        file_put_contents($path, $response->getContent());
        $rows = XlsxSheet::rows($path, 20, 'inventory rows');
        unlink($path);

        $this->assertSame(['Product', 'Variant', 'SKU', 'Location', 'Qty', 'Purchase price', 'Retail price', 'Markup'], $rows[0]);
        $this->assertSame('Organic kibble', $rows[1][0]);
        $this->assertSame('Makati Branch', $rows[1][3]);
        $this->assertSame('12', $rows[1][4]);
        $this->assertCount(2, $rows);
    }

    public function test_stock_count_export_leaves_the_count_blank(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $brand = Brand::factory()->create(['name' => 'Pluf']);
        $this->stock($branch, 'Organic kibble', null, $brand, 12);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/export?format=xlsx&sheet=count');

        $response->assertOk();
        $this->assertStringContainsString('inventory-count-'.now()->toDateString().'.xlsx', (string) $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'inventory-count');
        file_put_contents($path, $response->getContent());
        $rows = XlsxSheet::rows($path, 20, 'inventory rows');
        unlink($path);

        $this->assertSame(['Inventory Count', '', '', '', '', '', ''], $rows[0]);
        $this->assertSame([now()->format('F j, Y'), '', '', '', '', '', ''], $rows[1]);
        $this->assertSame(['Brand', 'Product', 'Variant', 'SKU', 'Location', 'Expected', 'Count'], $rows[2]);
        $this->assertSame('Pluf', $rows[3][0]);
        $this->assertSame('Organic kibble', $rows[3][1]);
        $this->assertSame('Makati Branch', $rows[3][4]);
        $this->assertSame('12', $rows[3][5]);
        $this->assertSame('', $rows[3][6]);
        $this->assertNotContains('Purchase price', $rows[2]);
    }

    public function test_stock_count_export_lists_products_without_stock_below_the_count(): void
    {
        $makati = Branch::factory()->create(['name' => 'Makati Branch']);
        $bgc = Branch::factory()->create(['name' => 'BGC Branch']);
        $pluf = Brand::factory()->create(['name' => 'Pluf']);
        $acme = Brand::factory()->create(['name' => 'Acme']);
        $kibble = CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Organic kibble',
            'brand_id' => $pluf->id,
        ]))->create(['name' => 'Default', 'sku' => 'KIB-001']);
        BranchInventory::factory()->create([
            'branch_id' => $makati->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 12,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $bgc->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 0,
        ]);
        $treats = CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Dry treats',
            'brand_id' => $acme->id,
        ]))->create(['name' => 'Default', 'sku' => 'TREAT-1']);
        BranchInventory::factory()->create([
            'branch_id' => $makati->id,
            'catalog_item_variant_id' => $treats->id,
            'quantity' => 0,
        ]);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Leather leash',
        ]))->create(['name' => 'Default', 'sku' => 'LEASH-1']);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->service()->create([
            'name' => 'Grooming',
        ]))->create(['name' => 'Default']);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->inactive()->create([
            'name' => 'Old toy',
        ]))->create(['name' => 'Default']);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Retired collar',
        ]))->create(['name' => 'Default', 'is_active' => false]);

        $rows = $this->exportedRows('/backoffice/inventory/export?format=xlsx&sheet=count');
        [$onHand, $unstocked] = $this->stockCountSections($rows);

        $this->assertSame([
            ['Pluf', 'Organic kibble', 'Default', 'KIB-001', 'Makati Branch', '12', ''],
        ], $onHand);
        $this->assertSame([
            ['Acme', 'Dry treats', 'Default', 'TREAT-1', 'Makati Branch', '0', ''],
            ['Pluf', 'Organic kibble', 'Default', 'KIB-001', 'BGC Branch', '0', ''],
            ['—', 'Leather leash', 'Default', 'LEASH-1', '—', '0', ''],
        ], $unstocked);
        $this->assertNotContains('Grooming', array_column($rows, 1));
        $this->assertNotContains('Old toy', array_column($rows, 1));
        $this->assertNotContains('Retired collar', array_column($rows, 1));
    }

    public function test_stock_count_export_lists_products_missing_from_the_selected_branch(): void
    {
        $makati = Branch::factory()->create(['name' => 'Makati Branch']);
        $bgc = Branch::factory()->create(['name' => 'BGC Branch']);
        $this->stock($bgc, 'Organic kibble', null, null, 4);
        $this->stock($makati, 'Dry treats', null, null, 5);
        CatalogItemVariant::factory()->for(CatalogItem::factory()->create([
            'name' => 'Leather leash',
        ]))->create(['name' => 'Default', 'sku' => 'LEASH-1']);

        $rows = $this->exportedRows('/backoffice/inventory/export?format=xlsx&sheet=count&branch_id='.$makati->id);
        [$onHand, $unstocked] = $this->stockCountSections($rows);

        $this->assertSame(['Dry treats'], array_column($onHand, 1));
        $this->assertSame(['Leather leash', 'Organic kibble'], array_column($unstocked, 1));
        $this->assertSame(['Makati Branch', 'Makati Branch'], array_column($unstocked, 4));
        $this->assertSame(['0', '0'], array_column($unstocked, 5));
        $this->assertSame(['', ''], array_column($unstocked, 6));
    }

    public function test_stock_count_pdf_is_a_download(): void
    {
        $branch = Branch::factory()->create();
        $this->stock($branch, 'Organic kibble', null, null, 4);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/export?format=pdf&sheet=count');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inventory-count-'.now()->toDateString().'.pdf', (string) $response->headers->get('content-disposition'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+1008(?:\.0+)?\s+612(?:\.0+)?\s*\]/', $content);
    }

    public function test_inventory_export_rejects_an_unknown_sheet(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->get('/backoffice/inventory/export?format=pdf&sheet=profit')
            ->assertRedirect('/backoffice/inventory')
            ->assertSessionHasErrors(['sheet' => 'Choose profit pricing or a stock count.']);
    }

    /**
     * @return list<list<string>>
     */
    private function exportedRows(string $url): array
    {
        $response = $this->actingAsEmployee()->get($url);

        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'inventory-count');
        file_put_contents($path, $response->getContent());
        $rows = XlsxSheet::rows($path, 20, 'inventory rows');
        unlink($path);

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: list<list<string>>, 1: list<list<string>>}
     */
    private function stockCountSections(array $rows): array
    {
        $onHand = [];
        $unstocked = [];
        $below = false;

        foreach ($rows as $row) {
            if ($row[0] === 'No stock') {
                $below = true;

                continue;
            }

            if ($row[0] === 'Brand' || $row[1] === '') {
                continue;
            }

            if ($below) {
                $unstocked[] = $row;
            } else {
                $onHand[] = $row;
            }
        }

        return [$onHand, $unstocked];
    }

    private function stock(Branch $branch, string $name, ?Category $category, ?Brand $brand, int $quantity): void
    {
        $item = CatalogItem::factory()->create([
            'name' => $name,
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create();
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }

    private function unassignedStock(string $name, ?Category $category, ?Brand $brand, int $quantity): void
    {
        $item = CatalogItem::factory()->create([
            'name' => $name,
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->confirmed()->create()->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $quantity,
            'received_quantity' => $quantity,
        ]);
    }
}
