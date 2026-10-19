<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\PosCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_category(): void
    {
        $response = $this->post('/backoffice/shop/categories', ['name' => 'Food']);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_empty_name_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories', []);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'name' => 'Enter a category name.',
            'pos_category_id' => 'Choose a POS category.',
        ]);
    }

    public function test_category_is_created(): void
    {
        $posCategory = PosCategory::factory()->create(['name' => 'Retail']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog/create')->post('/backoffice/shop/categories', [
            'name' => 'Food',
            'pos_category_id' => $posCategory->id,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog/create');
        $response->assertSessionHas('status', 'Category created.');
        $response->assertSessionHas('created_category', 'Food');
        $this->assertDatabaseHas('categories', [
            'name' => 'Food',
            'pos_category_id' => $posCategory->id,
        ]);
    }

    public function test_category_can_be_created_from_the_purchase_order_page(): void
    {
        $posCategory = PosCategory::factory()->create();

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/purchase-orders/create')
            ->post('/backoffice/shop/categories', [
                'name' => 'Treats',
                'pos_category_id' => $posCategory->id,
            ]);

        $response->assertRedirect('/backoffice/inventory/purchase-orders/create');
        $response->assertSessionHas('created_category', 'Treats');
        $this->assertDatabaseHas('categories', [
            'name' => 'Treats',
            'pos_category_id' => $posCategory->id,
        ]);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $existing = Category::factory()->create(['name' => 'Food']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories', [
            'name' => 'Food',
            'pos_category_id' => $existing->pos_category_id,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'name' => 'That category already exists.',
        ]);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_category_can_be_updated(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->put("/backoffice/shop/categories/{$category->id}", [
            'name' => 'Treats',
            'pos_category_id' => $category->pos_category_id,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Treats',
        ]);
    }

    public function test_category_can_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Food']);
        CatalogItem::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->delete("/backoffice/shop/categories/{$category->id}");

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('catalog_items', ['category_id' => null]);
    }
}
