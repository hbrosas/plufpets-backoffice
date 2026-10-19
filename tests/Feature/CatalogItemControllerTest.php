<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_catalog(): void
    {
        $response = $this->get('/backoffice/shop/catalog');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_catalog_items(): void
    {
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        CatalogItemVariant::factory()->for($item)->create(['sku' => 'KIB-001']);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Catalog/Index')
            ->where('catalogItems.data.0.name', 'Organic kibble')
            ->where('catalogItems.data.0.variant_count', 1));
    }

    public function test_catalog_can_be_filtered_by_type_and_search(): void
    {
        $kibble = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        CatalogItemVariant::factory()->for($kibble)->create(['sku' => 'KIB-001']);

        $groom = CatalogItem::factory()->service()->create(['name' => 'Full groom']);
        CatalogItemVariant::factory()->for($groom)->create(['sku' => 'GRM-001']);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog?type=service&search=groom');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('catalogItems.data', 1)
            ->where('catalogItems.data.0.name', 'Full groom')
            ->where('filters.type', 'service')
            ->where('filters.search', 'groom'));
    }

    public function test_catalog_can_be_filtered_by_barcode(): void
    {
        $kibble = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        CatalogItemVariant::factory()->for($kibble)->create(['barcode' => '4800123456789']);
        CatalogItem::factory()->create(['name' => 'Cat treats']);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog?search=4800123456789');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('catalogItems.data', 1)
            ->where('catalogItems.data.0.name', 'Organic kibble')
            ->where('filters.search', '4800123456789'));
    }

    public function test_create_form_lists_existing_categories_and_brands(): void
    {
        Category::factory()->create(['name' => 'Food']);
        Brand::factory()->create(['name' => 'Pluf Pets']);
        Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium'],
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Catalog/Create')
            ->where('categories', ['Food'])
            ->where('brands', ['Pluf Pets'])
            ->where('modifiers.0.name', 'Gift wrap')
            ->where('modifiers.0.options', ['Standard', 'Premium']));
    }

    public function test_empty_create_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', []);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors([
            'type' => 'Choose whether this is a product or a service.',
            'name' => 'Enter a name.',
            'variants' => 'Add at least one variant.',
        ]);
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_product_without_category_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'price' => '29.90', 'is_active' => true],
            ],
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors([
            'category_name' => 'Enter a category.',
        ]);
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_valid_product_is_created_with_variants(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'brand_name' => 'Pluf Pets',
            'description' => 'Daily dry food',
            'is_active' => true,
            'variants' => [
                [
                    'name' => '1kg',
                    'sku' => 'KIB-001',
                    'barcode' => '4800123456789',
                    'price' => '29.90',
                    'is_active' => true,
                    'image' => UploadedFile::fake()->image('kibble-1kg.jpg'),
                ],
                [
                    'name' => '5kg',
                    'sku' => 'KIB-005',
                    'barcode' => '4800555555555',
                    'price' => '99.00',
                    'is_active' => true,
                    'image' => UploadedFile::fake()->image('kibble-5kg.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Catalog item created.');
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Organic kibble',
            'type' => CatalogItemType::Product->value,
        ]);
        $this->assertDatabaseHas('categories', ['name' => 'Food']);
        $this->assertDatabaseHas('brands', ['name' => 'Pluf Pets']);
        $this->assertDatabaseHas('catalog_item_variants', [
            'name' => '1kg',
            'sku' => 'KIB-001',
            'barcode' => '4800123456789',
            'price' => '29.90',
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'name' => '5kg',
            'sku' => 'KIB-005',
        ]);
        $this->assertNotNull(
            CatalogItemVariant::query()->where('sku', 'KIB-001')->value('image_path'),
        );
        $this->assertNotNull(
            CatalogItemVariant::query()->where('sku', 'KIB-005')->value('image_path'),
        );
    }

    public function test_valid_service_is_created_without_category(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Service->value,
            'name' => 'Full groom',
            'is_active' => true,
            'variants' => [
                ['name' => 'Standard', 'sku' => 'GRM-001', 'price' => '45.00', 'is_active' => true],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Full groom',
            'type' => CatalogItemType::Service->value,
            'category_id' => null,
        ]);
    }

    public function test_duplicate_variant_sku_is_rejected(): void
    {
        $item = CatalogItem::factory()->create();
        CatalogItemVariant::factory()->for($item)->create(['sku' => 'KIB-001']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Another kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'sku' => 'KIB-001', 'price' => '19.00', 'is_active' => true],
            ],
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors('variants.0.sku');
        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_duplicate_variant_barcode_is_rejected(): void
    {
        $item = CatalogItem::factory()->create();
        CatalogItemVariant::factory()->for($item)->create(['barcode' => '4800123456789']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Another kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'barcode' => '4800123456789', 'price' => '19.00', 'is_active' => true],
            ],
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors([
            'variants.0.barcode' => 'That barcode is already used.',
        ]);
        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_catalog_item_can_be_updated(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        $brand = Brand::factory()->create(['name' => 'Pluf Pets']);
        $item = CatalogItem::factory()->create([
            'name' => 'Organic kibble',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'sku' => 'KIB-001',
            'price' => '29.90',
        ]);

        $response = $this->actingAsEmployee()->put("/backoffice/shop/catalog/{$item->id}", [
            'type' => CatalogItemType::Product->value,
            'name' => 'Premium kibble',
            'category_name' => 'Food',
            'brand_name' => 'Pluf Pets',
            'description' => 'Updated formula',
            'is_active' => false,
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => '1kg',
                    'sku' => 'KIB-001',
                    'price' => '32.50',
                    'is_active' => true,
                ],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Catalog item updated.');
        $this->assertDatabaseHas('catalog_items', [
            'id' => $item->id,
            'name' => 'Premium kibble',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'id' => $variant->id,
            'price' => '32.50',
        ]);
    }

    public function test_catalog_item_can_be_updated_through_method_spoofed_post(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        $item = CatalogItem::factory()->create([
            'name' => 'Organic kibble',
            'category_id' => $category->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'sku' => 'KIB-001',
            'price' => '29.90',
        ]);

        $response = $this->actingAsEmployee()->post("/backoffice/shop/catalog/{$item->id}", [
            '_method' => 'put',
            'type' => CatalogItemType::Product->value,
            'name' => 'Premium kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => '1kg',
                    'sku' => 'KIB-001',
                    'price' => '32.50',
                    'is_active' => true,
                    'image' => UploadedFile::fake()->image('kibble.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseHas('catalog_items', [
            'id' => $item->id,
            'name' => 'Premium kibble',
        ]);
        $this->assertNotNull($variant->fresh()->image_path);
    }

    public function test_variant_image_is_saved_and_can_be_removed(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create(['name' => 'Food']);
        $item = CatalogItem::factory()->create([
            'name' => 'Organic kibble',
            'category_id' => $category->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'sku' => 'KIB-001',
            'price' => '29.90',
        ]);

        $this->actingAsEmployee()->put("/backoffice/shop/catalog/{$item->id}", [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => '1kg',
                    'sku' => 'KIB-001',
                    'price' => '29.90',
                    'is_active' => true,
                    'image' => UploadedFile::fake()->image('kibble.jpg'),
                ],
            ],
        ])->assertRedirect(route('shop.catalog.index'));

        $path = $variant->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAsEmployee()->put("/backoffice/shop/catalog/{$item->id}", [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                [
                    'id' => $variant->id,
                    'name' => '1kg',
                    'sku' => 'KIB-001',
                    'price' => '29.90',
                    'is_active' => true,
                    'remove_image' => true,
                ],
            ],
        ])->assertRedirect(route('shop.catalog.index'));

        $this->assertNull($variant->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_product_is_created_with_enabled_modifiers(): void
    {
        $wrap = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard', 'Premium'],
        ]);
        $gravy = Modifier::factory()->create([
            'name' => 'Extra gravy',
            'options' => ['Small'],
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'price' => '29.90', 'is_active' => true],
            ],
            'modifier_ids' => [$wrap->id, $gravy->id],
            'modifier_prices' => [
                ['modifier_id' => $wrap->id, 'option' => 'Standard', 'price' => '10.00'],
                ['modifier_id' => $wrap->id, 'option' => 'Premium', 'price' => '25.00'],
                ['modifier_id' => $gravy->id, 'option' => 'Small', 'price' => '5.00'],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseHas('catalog_item_modifier', [
            'modifier_id' => $wrap->id,
        ]);
        $this->assertDatabaseHas('catalog_item_modifier', [
            'modifier_id' => $gravy->id,
        ]);
        $this->assertDatabaseHas('catalog_item_modifier_option_prices', [
            'modifier_id' => $wrap->id,
            'option' => 'Standard',
            'price' => '10.00',
        ]);
        $this->assertDatabaseHas('catalog_item_modifier_option_prices', [
            'modifier_id' => $wrap->id,
            'option' => 'Premium',
            'price' => '25.00',
        ]);
    }

    public function test_modifier_option_prices_can_differ_by_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $wrap = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard'],
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'has_branch_pricing' => true,
            'variants' => [
                ['name' => '1kg', 'price' => '29.90', 'is_active' => true],
            ],
            'modifier_ids' => [$wrap->id],
            'modifier_prices' => [
                [
                    'modifier_id' => $wrap->id,
                    'option' => 'Standard',
                    'price' => '10.00',
                    'branch_prices' => [
                        ['branch_id' => $branch->id, 'price' => '12.50'],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseHas('catalog_item_modifier_option_prices', [
            'modifier_id' => $wrap->id,
            'option' => 'Standard',
            'price' => '10.00',
        ]);
        $this->assertDatabaseHas('branch_modifier_option_prices', [
            'branch_id' => $branch->id,
            'price' => '12.50',
        ]);
    }

    public function test_option_price_is_rejected_when_modifier_is_not_enabled(): void
    {
        $wrap = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Standard'],
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'price' => '29.90', 'is_active' => true],
            ],
            'modifier_prices' => [
                ['modifier_id' => $wrap->id, 'option' => 'Standard', 'price' => '10.00'],
            ],
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors('modifier_prices.0.modifier_id');
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_unknown_modifier_cannot_be_enabled(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'variants' => [
                ['name' => '1kg', 'price' => '29.90', 'is_active' => true],
            ],
            'modifier_ids' => [999],
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHasErrors('modifier_ids.0');
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_product_can_use_different_prices_per_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog', [
            'type' => CatalogItemType::Product->value,
            'name' => 'Organic kibble',
            'category_name' => 'Food',
            'is_active' => true,
            'has_branch_pricing' => true,
            'variants' => [
                [
                    'name' => '1kg',
                    'price' => '29.90',
                    'is_active' => true,
                    'branch_prices' => [
                        ['branch_id' => $branch->id, 'price' => '32.00'],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Organic kibble',
            'has_branch_pricing' => true,
        ]);
        $this->assertDatabaseHas('branch_variant_prices', [
            'branch_id' => $branch->id,
            'price' => '32.00',
        ]);
    }

    public function test_catalog_item_can_be_deleted(): void
    {
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        CatalogItemVariant::factory()->for($item)->create();

        $response = $this->actingAsEmployee()->delete("/backoffice/shop/catalog/{$item->id}");

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Catalog item deleted.');
        $this->assertDatabaseMissing('catalog_items', [
            'id' => $item->id,
        ]);
    }

    public function test_guest_is_redirected_from_catalog_item_view(): void
    {
        $item = CatalogItem::factory()->create();

        $this->get("/backoffice/shop/catalog/{$item->id}")->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_catalog_supply_labels(): void
    {
        $item = CatalogItem::factory()->create();

        $this->get("/backoffice/shop/catalog/{$item->id}/supplies/purchase-1/labels")->assertRedirect(route('login'));
    }

    public function test_catalog_item_view_lists_supplies(): void
    {
        $this->travelTo('2026-10-18 08:15:00');

        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create(['name' => 'Default', 'price' => 120]);
        $order = PurchaseOrder::factory()->confirmed()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'catalog_item_variant_id' => $variant->id,
            'unit_cost' => 80,
        ]);
        $receipt = PurchaseOrderReceipt::factory()->create([
            'purchase_order_id' => $order->id,
            'received_at' => now(),
        ]);
        $receiptItem = PurchaseOrderReceiptItem::factory()->create([
            'purchase_order_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'quantity' => 8,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/shop/catalog/{$item->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Catalog/Show')
            ->where('catalogItem.name', 'Organic kibble')
            ->where('supplies.0.id', 'purchase-'.$receiptItem->id)
            ->where('supplies.0.quantity', 8)
            ->where('supplies.0.price', '₱80.00'));
    }

    public function test_supply_labels_print_one_sticker_per_unit(): void
    {
        $this->travelTo('2026-10-18 08:15:00');

        $item = CatalogItem::factory()->create(['name' => "Kibble <script>alert('xss')</script>"]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'barcode' => '123456789012',
            'price' => 257.14,
        ]);
        $conversion = StockConversion::factory()->create([
            'from_catalog_item_variant_id' => CatalogItemVariant::factory(),
            'to_catalog_item_variant_id' => $variant->id,
            'from_quantity' => 1,
            'to_quantity' => 7,
            'unit_cost' => 100,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/shop/catalog/{$item->id}/supplies/conversion-{$conversion->id}/labels");

        $response->assertOk();
        $response->assertSee('40mm', false);
        $response->assertSee('30mm', false);
        $response->assertSee('1kg', false);
        $response->assertSee('₱257.14', false);
        $response->assertSee('123456789012', false);
        $response->assertSee('Supply Date: Oct 18, 2026', false);
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
        $this->assertSame(7, substr_count($response->getContent(), 'class="label"'));

        $html = $response->getContent();
        $barcodePosition = strpos($html, 'class="barcode"');
        $namePosition = strpos($html, 'class="name"');
        $pricePosition = strpos($html, 'class="price"');
        $datePosition = strpos($html, 'class="dates"');

        $this->assertNotFalse($barcodePosition);
        $this->assertTrue($barcodePosition < $namePosition);
        $this->assertTrue($namePosition < $pricePosition);
        $this->assertTrue($pricePosition < $datePosition);
    }

    public function test_supply_labels_are_not_found_for_another_catalog_item(): void
    {
        $item = CatalogItem::factory()->create();
        CatalogItemVariant::factory()->for($item)->create();
        $other = CatalogItem::factory()->create();
        $otherVariant = CatalogItemVariant::factory()->for($other)->create();
        $conversion = StockConversion::factory()->create([
            'from_catalog_item_variant_id' => CatalogItemVariant::factory(),
            'to_catalog_item_variant_id' => $otherVariant->id,
            'to_quantity' => 3,
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/shop/catalog/{$item->id}/supplies/conversion-{$conversion->id}/labels");

        $response->assertNotFound();
    }
}
