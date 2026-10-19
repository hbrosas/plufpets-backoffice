<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PosCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogMetaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_categories_and_brands(): void
    {
        $response = $this->get('/backoffice/shop/catalog-meta');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_categories_and_brands(): void
    {
        $posCategory = PosCategory::factory()->create(['name' => 'Retail']);
        Category::factory()->create([
            'name' => 'Food',
            'pos_category_id' => $posCategory->id,
        ]);
        Brand::factory()->create(['name' => 'Pluf Pets']);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/catalog-meta');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/CatalogMeta/Index')
            ->where('categories.0.name', 'Food')
            ->where('categories.0.pos_category_name', 'Retail')
            ->where('categories.0.item_count', 0)
            ->where('brands.0.name', 'Pluf Pets')
            ->where('brands.0.item_count', 0));
    }
}
