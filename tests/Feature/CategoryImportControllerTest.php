<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CategoryImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_import_categories(): void
    {
        $response = $this->post('/backoffice/shop/categories/import', [
            'file' => $this->csvFile("name,pos_category,show_on_shop\nFood,Retail,1\n"),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_template_can_be_downloaded(): void
    {
        $response = $this->actingAsEmployee()->get('/backoffice/shop/categories/import/template');

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=categories-template.csv');
        $this->assertStringContainsString(
            "name,pos_category,show_on_shop\nFood,Retail,1\n",
            $response->streamedContent(),
        );
    }

    public function test_missing_file_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/catalog-meta')->post('/backoffice/shop/categories/import', []);

        $response->assertRedirect('/backoffice/shop/catalog-meta');
        $response->assertSessionHasErrors([
            'file' => 'Choose a CSV file to upload.',
        ]);
    }

    public function test_categories_are_created(): void
    {
        $retail = PosCategory::factory()->create(['name' => 'Retail']);
        PosCategory::factory()->create(['name' => 'Services']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/categories/import', [
            'file' => $this->csvFile(
                "name,pos_category,show_on_shop\n".
                "Food,retail,1\n".
                "Treats,Retail,\n".
                "Grooming,Services,0\n",
            ),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('status', 'Imported 3 categories.');
        $this->assertDatabaseHas('categories', [
            'name' => 'Food',
            'pos_category_id' => $retail->id,
            'show_on_shop' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Treats',
            'pos_category_id' => $retail->id,
            'show_on_shop' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Grooming',
            'show_on_shop' => false,
        ]);
    }

    public function test_existing_category_is_updated(): void
    {
        $retail = PosCategory::factory()->create(['name' => 'Retail']);
        $services = PosCategory::factory()->create(['name' => 'Services']);
        $category = Category::factory()->create([
            'name' => 'Food',
            'pos_category_id' => $retail->id,
            'show_on_shop' => true,
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/categories/import', [
            'file' => $this->csvFile("name,pos_category,show_on_shop\nfood,Services,0\n"),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('status', 'Imported 1 category.');
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Food',
            'pos_category_id' => $services->id,
            'show_on_shop' => false,
        ]);
    }

    public function test_unknown_pos_category_is_skipped(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/categories/import', [
            'file' => $this->csvFile("name,pos_category,show_on_shop\nFood,Missing,1\n"),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('status', 'Imported 0 categories. Some rows were skipped.');
        $response->assertSessionHas('import_errors', [
            ['row' => 2, 'message' => 'That POS category does not exist.'],
        ]);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_duplicate_name_in_the_file_is_skipped(): void
    {
        $retail = PosCategory::factory()->create(['name' => 'Retail']);
        PosCategory::factory()->create(['name' => 'Services']);

        $response = $this->actingAsEmployee()->post('/backoffice/shop/categories/import', [
            'file' => $this->csvFile(
                "name,pos_category,show_on_shop\n".
                "Food,Retail,1\n".
                "food,Services,0\n",
            ),
        ]);

        $response->assertRedirect(route('shop.catalog-meta.index'));
        $response->assertSessionHas('import_errors', [
            ['row' => 3, 'message' => 'This name is duplicated in the file.'],
        ]);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', [
            'name' => 'Food',
            'pos_category_id' => $retail->id,
            'show_on_shop' => true,
        ]);
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('categories.csv', $contents);
    }
}
