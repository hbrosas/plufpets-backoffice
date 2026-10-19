<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_brand(): void
    {
        $response = $this->post('/backoffice/shop/brands', ['name' => 'Pluf Pets']);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_empty_name_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/brands', []);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'name' => 'Enter a brand name.',
        ]);
    }

    public function test_brand_is_created_with_a_logo(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/brands', [
            'name' => 'Pluf Pets',
            'image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHas('status', 'Brand created.');
        $response->assertSessionHas('created_brand', 'Pluf Pets');
        $this->assertDatabaseHas('brands', ['name' => 'Pluf Pets']);

        $imagePath = Brand::query()->where('name', 'Pluf Pets')->value('image_path');
        $this->assertNotNull($imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_brand_can_be_created_from_the_purchase_order_page(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/shop/brands', [
                'name' => 'House Brand',
            ]);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $response->assertSessionHas('created_brand', 'House Brand');
        $this->assertDatabaseHas('brands', ['name' => 'House Brand']);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/brands', [
            'name' => 'Pluf Pets',
            'image' => UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'),
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_duplicate_brand_name_is_rejected(): void
    {
        Brand::factory()->create(['name' => 'Pluf Pets']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/brands', [
            'name' => 'Pluf Pets',
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'name' => 'That brand already exists.',
        ]);
    }

    public function test_brand_can_be_updated(): void
    {
        Storage::fake('public');
        $brand = Brand::factory()->create(['name' => 'Pluf Pets']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->put("/backoffice/shop/brands/{$brand->id}", [
            'name' => 'Pluf Co',
            'image' => UploadedFile::fake()->image('new-logo.jpg'),
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Pluf Co',
        ]);

        $imagePath = $brand->fresh()->image_path;
        $this->assertNotNull($imagePath);
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_brand_can_be_deleted(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('logo.png')->store('brand-logos', 'public');
        $brand = Brand::factory()->create([
            'name' => 'Pluf Pets',
            'image_path' => $path,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->delete("/backoffice/shop/brands/{$brand->id}");

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
