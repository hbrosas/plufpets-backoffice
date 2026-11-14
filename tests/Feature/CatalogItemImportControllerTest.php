<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\BranchVariantPrice;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogItemImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_import_catalog_items(): void
    {
        $response = $this->post('/backoffice/shop/catalog/import', [
            'file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_template_can_be_downloaded(): void
    {
        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog/import/template');

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=catalog-items-template.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString(
            'type,name,description,category,brand,variant_name,sku,barcode,price,is_active,variant_active',
            $content,
        );
        $this->assertStringContainsString(
            ',,,,,5kg,KIB-002,4800123456796,89.00,,1',
            $content,
        );
    }

    public function test_missing_file_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog')->post('/backoffice/shop/catalog/import', []);

        $response->assertRedirect('/backoffice/shop/catalog');
        $response->assertSessionHasErrors([
            'file' => 'Choose a CSV file to upload.',
        ]);
    }

    public function test_valid_csv_imports_products_and_services(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 2 catalog rows.');
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Organic kibble',
            'type' => CatalogItemType::Product->value,
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'sku' => 'KIB-001',
            'name' => '1kg',
        ]);
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Full groom',
            'type' => CatalogItemType::Service->value,
        ]);
        $this->assertDatabaseHas('categories', ['name' => 'Food']);
    }

    public function test_invalid_rows_are_skipped_and_reported(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n".
            "product,Another kibble,Daily dry food,Food,Pluf Pets,,KIB-002,4800222222222,12.00,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 1 catalog row. Some rows were skipped.');
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'Enter a variant name.'],
        ]);
        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_duplicate_sku_in_the_file_is_skipped(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n".
            "product,Another kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800222222222,12.00,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'This SKU is duplicated in the file.'],
        ]);
        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_duplicate_barcode_in_the_file_is_skipped(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n".
            "product,Another kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-002,4800123456789,12.00,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'This barcode is duplicated in the file.'],
        ]);
        $this->assertDatabaseCount('catalog_items', 1);
    }

    public function test_existing_sku_is_skipped_during_import(): void
    {
        $item = CatalogItem::factory()->create(['name' => 'Existing kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create(['sku' => 'KIB-001']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'That SKU is already used.'],
        ]);
        $this->assertDatabaseCount('catalog_items', 2);
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Full groom',
        ]);
        $this->assertDatabaseMissing('catalog_items', [
            'name' => 'Organic kibble',
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'id' => $variant->id,
            'sku' => 'KIB-001',
            'name' => $variant->name,
        ]);
    }

    public function test_blank_name_adds_a_variant_to_the_product_above(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active,variant_active\n".
            "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1,1\n".
            ",,,,,5kg,KIB-002,4800123456796,89.00,,0\n".
            "service,Full groom,Bath and haircut,Grooming,,Standard,GRM-001,4800987654321,45.00,1,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 3 catalog rows.');
        $this->assertDatabaseCount('catalog_items', 2);
        $item = CatalogItem::query()->where('name', 'Organic kibble')->first();
        $this->assertNotNull($item);
        $this->assertTrue($item->is_active);
        $this->assertSame('Daily dry food', $item->description);
        $this->assertDatabaseHas('catalog_item_variants', [
            'catalog_item_id' => $item->id,
            'name' => '1kg',
            'sku' => 'KIB-001',
            'price' => '29.90',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'catalog_item_id' => $item->id,
            'name' => '5kg',
            'sku' => 'KIB-002',
            'price' => '89.00',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Full groom',
            'type' => CatalogItemType::Service->value,
        ]);
    }

    public function test_columns_can_be_in_any_order(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "note,price,sku,variant_name,name,type,category,brand,description,barcode,is_active\n".
            "ignore,12.50,KIB-009,1kg,Travel bowl,product,Bowls,Pluf Pets,Collapsible,4800000000009,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 1 catalog row.');
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Travel bowl',
            'description' => 'Collapsible',
        ]);
        $this->assertDatabaseHas('catalog_item_variants', [
            'sku' => 'KIB-009',
            'name' => '1kg',
            'price' => '12.50',
        ]);
    }

    public function test_missing_column_rejects_the_file(): void
    {
        $csv = UploadedFile::fake()->createWithContent('catalog.csv', "type,name\nproduct,Kibble\n");

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog')->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog');
        $response->assertSessionHasErrors([
            'file' => 'The CSV must include these columns: type, name, description, category, brand, variant_name, sku, barcode, price, is_active.',
        ]);
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_blank_name_on_the_first_row_is_rejected(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            ",,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'Enter a name.'],
        ]);
        $this->assertDatabaseCount('catalog_items', 0);
    }

    public function test_new_product_without_a_category_is_rejected(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            "product,Organic kibble,Daily dry food,,,1kg,KIB-001,4800123456789,29.90,1\n".
            "service,Full groom,Bath and haircut,Grooming,,Standard,GRM-001,4800987654321,45.00,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 1 catalog row. Some rows were skipped.');
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'Enter a category.'],
        ]);
        $this->assertDatabaseMissing('catalog_items', ['name' => 'Organic kibble']);
        $this->assertDatabaseHas('catalog_items', ['name' => 'Full groom']);
    }

    public function test_existing_barcode_on_another_product_is_skipped(): void
    {
        $item = CatalogItem::factory()->create(['name' => 'Other kibble']);
        CatalogItemVariant::factory()->for($item)->create([
            'sku' => 'OTHER-001',
            'barcode' => '4800123456789',
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'That barcode is already used.'],
        ]);
        $this->assertDatabaseMissing('catalog_items', ['name' => 'Organic kibble']);
        $this->assertDatabaseHas('catalog_items', ['name' => 'Full groom']);
    }

    public function test_existing_product_and_matching_sku_are_updated(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        $brand = Brand::factory()->create(['name' => 'Pluf Pets']);
        $item = CatalogItem::factory()->create([
            'name' => 'Organic kibble',
            'description' => 'Old description',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'is_active' => true,
            'badge' => 'Sale',
            'allows_preorder' => false,
            'has_branch_pricing' => true,
        ]);
        $updated = CatalogItemVariant::factory()->for($item)->create([
            'name' => '1kg',
            'sku' => 'KIB-001',
            'barcode' => '111',
            'price' => 10,
            'is_active' => true,
            'image_path' => 'catalog-images/one.jpg',
        ]);
        $kept = CatalogItemVariant::factory()->for($item)->create([
            'name' => '2kg',
            'sku' => 'KIB-002',
            'barcode' => '222',
            'price' => 20,
            'is_active' => true,
        ]);
        $branchPrice = BranchVariantPrice::factory()->create([
            'catalog_item_variant_id' => $updated->id,
            'price' => 15,
        ]);
        $modifier = Modifier::factory()->create([
            'name' => 'Gift wrap',
            'options' => ['Gift wrap'],
        ]);
        $item->modifiers()->attach($modifier->id);
        CatalogItemModifierOptionPrice::factory()->create([
            'catalog_item_id' => $item->id,
            'modifier_id' => $modifier->id,
            'option' => 'Gift wrap',
            'price' => 5,
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active,variant_active\n".
            "product,Organic Kibble,Daily dry food,,,1kg,KIB-001,333,29.90,0,0\n".
            ",,,,,5kg,KIB-005,444,89.00,,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 2 catalog rows.');
        $this->assertDatabaseCount('catalog_items', 1);

        $item->refresh();
        $this->assertSame('Organic Kibble', $item->name);
        $this->assertSame('Daily dry food', $item->description);
        $this->assertSame($category->id, $item->category_id);
        $this->assertSame($brand->id, $item->brand_id);
        $this->assertFalse($item->is_active);
        $this->assertSame('Sale', $item->badge);
        $this->assertFalse($item->allows_preorder);
        $this->assertTrue($item->has_branch_pricing);
        $this->assertSame(3, $item->variants()->count());

        $updated->refresh();
        $this->assertSame('1kg', $updated->name);
        $this->assertSame('333', $updated->barcode);
        $this->assertSame('29.90', $updated->price);
        $this->assertFalse($updated->is_active);
        $this->assertSame('catalog-images/one.jpg', $updated->image_path);
        $this->assertDatabaseHas('branch_variant_prices', [
            'id' => $branchPrice->id,
            'catalog_item_variant_id' => $updated->id,
            'price' => '15.00',
        ]);

        $kept->refresh();
        $this->assertSame('2kg', $kept->name);
        $this->assertSame('222', $kept->barcode);
        $this->assertSame('20.00', $kept->price);
        $this->assertTrue($kept->is_active);

        $this->assertDatabaseHas('catalog_item_variants', [
            'catalog_item_id' => $item->id,
            'name' => '5kg',
            'sku' => 'KIB-005',
            'barcode' => '444',
            'price' => '89.00',
            'is_active' => true,
        ]);
        $this->assertTrue($item->modifiers()->whereKey($modifier->id)->exists());
        $this->assertDatabaseHas('catalog_item_modifier_option_prices', [
            'catalog_item_id' => $item->id,
            'modifier_id' => $modifier->id,
            'option' => 'Gift wrap',
            'price' => '5.00',
        ]);
    }

    private function csvFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'catalog.csv',
            "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n".
            "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n".
            "service,Full groom,Bath and haircut,Grooming,,Standard,GRM-001,4800987654321,45.00,1\n",
        );
    }
}
