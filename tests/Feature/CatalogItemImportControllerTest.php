<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
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
        $this->assertStringContainsString(
            'type,name,description,category,brand,variant_name,sku,barcode,price,is_active',
            $response->streamedContent(),
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
            "product,,Daily dry food,Food,Pluf Pets,1kg,KIB-002,4800222222222,12.00,1\n",
        );

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $csv,
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $response->assertSessionHas('status', 'Imported 1 catalog row. Some rows were skipped.');
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'Enter a name.'],
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
        CatalogItemVariant::factory()->for($item)->create(['sku' => 'KIB-001']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/catalog/import', [
            'file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('shop.catalog.index'));
        $this->assertDatabaseCount('catalog_items', 2);
        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Full groom',
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
