<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_pos_categories(): void
    {
        $this->get('/backoffice/pos/categories')->assertRedirect(route('login'));
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/pos/categories')->post('/backoffice/pos/categories', []);

        $response->assertRedirect('/backoffice/pos/categories');
        $response->assertSessionHasErrors([
            'name' => 'Enter a POS category name.',
        ]);
    }

    public function test_pos_category_is_created(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/pos/categories')->post('/backoffice/pos/categories', [
            'name' => 'Treats',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('pos.categories.index'));
        $this->assertDatabaseHas('pos_categories', ['name' => 'Treats']);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        PosCategory::factory()->create(['name' => 'Treats']);

        $response = $this->actingAsEmployee()->from('/backoffice/pos/categories')->post('/backoffice/pos/categories', [
            'name' => 'Treats',
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/pos/categories');
        $response->assertSessionHasErrors([
            'name' => 'That POS category already exists.',
        ]);
    }

    public function test_pos_category_lists_linked_shop_categories(): void
    {
        $posCategory = PosCategory::factory()->create(['name' => 'Food']);
        Category::factory()->create([
            'name' => 'Dry Food',
            'pos_category_id' => $posCategory->id,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/pos/categories');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.name', 'Food')
            ->where('categories.0.shop_categories.0', 'Dry Food')
            ->where('categories.0.shop_category_count', 1));
    }

    public function test_authenticated_employee_sees_pos_categories(): void
    {
        PosCategory::factory()->create(['name' => 'Grooming']);

        $response = $this->actingAsEmployee()->get('/backoffice/pos/categories');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Categories/Index')
            ->where('categories.0.name', 'Grooming'));
    }
}
