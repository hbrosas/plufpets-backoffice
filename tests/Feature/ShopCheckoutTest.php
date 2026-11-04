<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchVariantPrice;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PosOpenOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_selected_branch_catalog(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro', 'is_active' => true]);
        $food = Category::factory()->create(['name' => 'Food']);
        $grooming = Category::factory()->create(['name' => 'Grooming']);

        $kibble = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'category_id' => $food->id,
                'has_branch_pricing' => true,
            ]))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $greenheights->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 2,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $sanPedro->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 9,
        ]);
        BranchVariantPrice::factory()->create([
            'branch_id' => $greenheights->id,
            'catalog_item_variant_id' => $kibble->id,
            'price' => 80,
        ]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->service()->create([
                'name' => 'Bath',
                'category_id' => $grooming->id,
            ]))
            ->create(['name' => 'Default', 'price' => 250]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Treats',
                'category_id' => $food->id,
            ]))
            ->create(['name' => 'Default', 'price' => 40]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->inactive()->create(['name' => 'Hidden']))
            ->create(['name' => 'Default']);

        $this->get('/?branch='.$greenheights->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('filters.branch', $greenheights->id)
                ->where('products.0.name', 'Bath')
                ->where('products.0.price_label', '₱250.00')
                ->where('products.0.stock_label', 'In Stock')
                ->where('products.1.name', 'Kibble · Large')
                ->where('products.1.price_label', '₱80.00')
                ->where('products.1.stock_label', 'In Stock 2')
                ->where('products.2.name', 'Treats')
                ->where('products.2.stock_label', 'Out of Stock')
                ->where('products.2.in_stock', false)
                ->has('products', 3)
                ->has('categories', 2));

        $this->get('/?branch='.$sanPedro->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.1.price_label', '₱100.00')
                ->where('products.1.stock_label', 'In Stock 9'));

        $this->get('/?branch='.$greenheights->id.'&category='.$food->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 2)
                ->where('products.0.name', 'Kibble · Large'));

        $this->get('/?branch='.$greenheights->id.'&q=Kibble')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 1)
                ->where('products.0.name', 'Kibble · Large'));
    }

    public function test_hidden_categories_stay_off_the_public_shop(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $hidden = Category::factory()->create(['name' => 'Supplies', 'show_on_shop' => false]);
        $shown = Category::factory()->create(['name' => 'Food', 'show_on_shop' => true]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Leash',
                'category_id' => $hidden->id,
            ]))
            ->create(['name' => 'Default', 'price' => 90]);

        CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'category_id' => $shown->id,
            ]))
            ->create(['name' => 'Default', 'price' => 100]);

        $this->get('/?branch='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Food')
                ->has('products', 1)
                ->where('products.0.name', 'Kibble'));
    }

    public function test_guest_checkout_saves_the_branch_price_without_a_sale(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create([
                'name' => 'Kibble',
                'has_branch_pricing' => true,
            ]))
            ->create(['name' => 'Large', 'price' => 100]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
        BranchVariantPrice::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'price' => 80,
        ]);

        $response = $this->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 2, 'price' => 1],
            ],
        ]);

        $response->assertRedirect(route('home', ['branch' => $branch->id]));
        $response->assertSessionHas('checkout_success.total', '₱160.00');

        $order = PosOpenOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame('Ada Reyes · 09171234567', $order->name);
        $this->assertSame('160.00', $order->subtotal);
        $this->assertSame('Ada Reyes', $order->saved_by_name);
        $this->assertSame('', $order->saved_by_employee_number);
        $this->assertSame('80.00', $order->items[0]['price']);
        $this->assertSame(2, $order->items[0]['quantity']);
        $this->assertSame([], $order->items[0]['modifiers']);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
    }

    public function test_checkout_rejects_more_than_the_branch_has_in_stock(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['name' => 'Treats']))
            ->create(['name' => 'Default', 'price' => 40]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => 'Ada Reyes',
            'phone' => '09171234567',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 3],
            ],
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'items' => 'Only 1 left for Treats.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    public function test_checkout_requires_a_name_and_phone(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $variant = CatalogItemVariant::factory()->create(['price' => 50]);

        $response = $this->from('/')->post('/checkout', [
            'branch_id' => $branch->id,
            'name' => '',
            'phone' => '',
            'items' => [
                ['catalog_item_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'name' => 'Enter your name.',
            'phone' => 'Enter your phone number.',
        ]);
        $this->assertDatabaseCount('pos_open_orders', 0);
    }
}
