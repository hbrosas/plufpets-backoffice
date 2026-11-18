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

    public function test_category_can_be_hidden_from_the_public_shop(): void
    {
        $posCategory = PosCategory::factory()->create();

        $this->actingAsEmployee()->post('/backoffice/shop/categories', [
            'name' => 'Supplies',
            'pos_category_id' => $posCategory->id,
            'show_on_shop' => false,
        ]);

        $category = Category::query()->where('name', 'Supplies')->first();
        $this->assertNotNull($category);
        $this->assertFalse($category->show_on_shop);

        $this->actingAsEmployee()->put("/backoffice/shop/categories/{$category->id}", [
            'name' => 'Supplies',
            'pos_category_id' => $posCategory->id,
            'show_on_shop' => true,
        ]);

        $this->assertTrue($category->refresh()->show_on_shop);
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

    public function test_guest_is_redirected_from_bulk_category_update(): void
    {
        $this->post('/backoffice/shop/categories/bulk')->assertRedirect(route('login'));
    }

    public function test_bulk_category_update_requires_a_selection_and_action(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', []);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'ids' => 'Choose a category.',
            'action' => 'Choose an action.',
        ]);
    }

    public function test_bulk_update_rejects_an_unknown_category(): void
    {
        $posCategory = PosCategory::factory()->create();

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [999],
            'action' => 'pos_category',
            'pos_category_id' => $posCategory->id,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'ids.0' => 'Choose a category from the list.',
        ]);
    }

    public function test_bulk_pos_category_is_required(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$category->id],
            'action' => 'pos_category',
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'pos_category_id' => 'Choose a POS category.',
        ]);
        $this->assertSame($category->pos_category_id, $category->fresh()->pos_category_id);
    }

    public function test_bulk_pos_category_must_exist(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$category->id],
            'action' => 'pos_category',
            'pos_category_id' => 999,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'pos_category_id' => 'Choose a POS category.',
        ]);
        $this->assertSame($category->pos_category_id, $category->fresh()->pos_category_id);
    }

    public function test_bulk_visibility_requires_a_choice(): void
    {
        $category = Category::factory()->create(['show_on_shop' => true]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$category->id],
            'action' => 'visibility',
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'show_on_shop' => 'Choose public shop visibility.',
        ]);
        $this->assertTrue($category->fresh()->show_on_shop);
    }

    public function test_selected_categories_can_change_pos_category(): void
    {
        $retail = PosCategory::factory()->create(['name' => 'Retail']);
        $services = PosCategory::factory()->create(['name' => 'Services']);
        $first = Category::factory()->create(['name' => 'Food', 'pos_category_id' => $retail->id]);
        $second = Category::factory()->create(['name' => 'Treats', 'pos_category_id' => $retail->id]);
        $untouched = Category::factory()->create(['name' => 'Grooming', 'pos_category_id' => $retail->id]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$first->id, $second->id],
            'action' => 'pos_category',
            'pos_category_id' => $services->id,
        ]);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHas('status', 'POS category updated.');
        $this->assertSame($services->id, $first->fresh()->pos_category_id);
        $this->assertSame($services->id, $second->fresh()->pos_category_id);
        $this->assertSame($retail->id, $untouched->fresh()->pos_category_id);
    }

    public function test_selected_categories_can_change_public_shop_visibility(): void
    {
        $first = Category::factory()->create(['name' => 'Food', 'show_on_shop' => true]);
        $second = Category::factory()->create(['name' => 'Treats', 'show_on_shop' => false]);
        $untouched = Category::factory()->create(['name' => 'Grooming', 'show_on_shop' => true]);

        $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$first->id, $second->id],
            'action' => 'visibility',
            'show_on_shop' => false,
        ])->assertSessionHas('status', 'Public shop visibility updated.');

        $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/bulk', [
            'ids' => [$first->id],
            'action' => 'visibility',
            'show_on_shop' => true,
        ])->assertSessionHas('status', 'Public shop visibility updated.');

        $this->assertTrue($first->fresh()->show_on_shop);
        $this->assertFalse($second->fresh()->show_on_shop);
        $this->assertTrue($untouched->fresh()->show_on_shop);
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
