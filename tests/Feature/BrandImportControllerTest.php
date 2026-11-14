<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BrandImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_import_brands(): void
    {
        $response = $this->post('/backoffice/shop/brands/import', [
            'file' => $this->csvFile("name\nPluf Pets\n"),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_template_can_be_downloaded(): void
    {
        $response = $this->actingAsEmployee()->get('/backoffice/shop/brands/import/template');

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=brands-template.csv');
        $this->assertStringContainsString("name\nPluf Pets\n", $response->streamedContent());
    }

    public function test_missing_file_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/brands/import', []);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'file' => 'Choose a CSV file to upload.',
        ]);
    }

    public function test_brands_are_created(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/brands/import', [
            'file' => $this->csvFile("note,name\nhello,Pluf Pets\n,House Brand\n"),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('status', 'Imported 2 brands.');
        $this->assertDatabaseHas('brands', ['name' => 'Pluf Pets']);
        $this->assertDatabaseHas('brands', ['name' => 'House Brand']);
    }

    public function test_existing_brand_is_skipped(): void
    {
        Brand::factory()->create(['name' => 'Pluf Pets']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/brands/import', [
            'file' => $this->csvFile("name\npluf pets\nHouse Brand\n"),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('status', 'Imported 1 brand. Some rows were skipped.');
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'That brand already exists.'],
        ]);
        $this->assertDatabaseCount('brands', 2);
        $this->assertDatabaseHas('brands', ['name' => 'House Brand']);
    }

    public function test_duplicate_name_in_the_file_is_skipped(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/brands/import', [
            'file' => $this->csvFile("name\nPluf Pets\npluf pets\n"),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'This name is duplicated in the file.'],
        ]);
        $this->assertDatabaseCount('brands', 1);
        $this->assertDatabaseHas('brands', ['name' => 'Pluf Pets']);
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('brands.csv', $contents);
    }
}
