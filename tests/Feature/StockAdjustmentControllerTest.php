<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchVariantPrice;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_stock_adjustments(): void
    {
        $this->get('/backoffice/inventory/adjustments')->assertRedirect(route('login'));
        $this->post('/backoffice/inventory/adjustments')->assertRedirect(route('login'));
    }

    public function test_brand_adjustment_lists_sold_and_profit_for_that_brand(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $brand = Brand::factory()->create(['name' => 'Pluf']);
        $other = Brand::factory()->create(['name' => 'Other']);
        $kibble = $this->product($branch, 'Organic kibble', $brand, null, 10, 120);
        $this->product($branch, 'Rope toy', $other, null, 4, 50);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->create()->id,
            'catalog_item_variant_id' => $kibble->id,
            'unit_cost' => 80,
        ]);
        $sale = PosSale::factory()->create(['branch_id' => $branch->id]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 2,
            'unit_price' => 120,
            'line_total' => 240,
            'is_free' => false,
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/inventory/adjustments?scope=brand&brand_id='.$brand->id.'&branch_id='.$branch->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Adjustments')
                ->has('lines', 1)
                ->where('lines.0.product', 'Organic kibble')
                ->where('lines.0.quantity', 10)
                ->where('lines.0.sold', 2)
                ->where('lines.0.profit', '₱80.00')
                ->where('lines.0.retail_price', '₱120.00'));
    }

    public function test_category_adjustment_changes_quantities_without_creating_a_sale(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $food = Category::factory()->create(['name' => 'Food']);
        $kibble = $this->product($branch, 'Organic kibble', null, $food, 10, 120);
        BranchInventory::factory()->create([
            'branch_id' => $otherBranch->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 3,
        ]);
        $treat = $this->product($branch, 'Training treat', null, $food, 1, 40);

        $this->actingAsEmployee()
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'category',
                'category_id' => $food->id,
                'record_as_sale' => false,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 8],
                    ['catalog_item_variant_id' => $treat->id, 'quantity' => 6],
                ],
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('status', 'Stock adjusted.');

        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 8,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $treat->id,
            'quantity' => 6,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $otherBranch->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('stock_adjustments', [
            'branch_id' => $branch->id,
            'scope' => 'category',
            'category_id' => $food->id,
            'record_as_sale' => false,
            'pos_sale_id' => null,
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/inventory/products/'.$kibble->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('history', fn (mixed $history): bool => collect($history)->contains(
                    fn (array $row): bool => $row['type'] === 'Adjustment' && $row['quantity'] === -2,
                )));
    }

    public function test_decreased_quantities_can_be_recorded_as_one_sale(): void
    {
        $branch = Branch::factory()->create();
        $brand = Brand::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', $brand, null, 10, 100);
        $treat = $this->product($branch, 'Training treat', $brand, null, 4, 50);
        BranchVariantPrice::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $kibble->id,
            'price' => 90,
        ]);

        $this->actingAsEmployee()
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'brand',
                'brand_id' => $brand->id,
                'record_as_sale' => 1,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 7],
                    ['catalog_item_variant_id' => $treat->id, 'quantity' => 4],
                ],
            ])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('status', 'Stock adjusted and recorded as a sale.');

        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 7,
        ]);
        $sale = PosSale::query()->firstOrFail();
        $this->assertNull($sale->pos_shift_id);
        $this->assertSame('270.00', $sale->total);
        $this->assertSame($branch->id, $sale->branch_id);
        $this->assertDatabaseHas('pos_sale_items', [
            'pos_sale_id' => $sale->id,
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 3,
            'unit_price' => '90.00',
            'line_total' => '270.00',
        ]);
        $this->assertDatabaseMissing('pos_sale_items', [
            'catalog_item_variant_id' => $treat->id,
        ]);
        $this->assertDatabaseHas('pos_sale_payments', [
            'pos_sale_id' => $sale->id,
            'amount' => '270.00',
            'is_cash' => true,
        ]);
        $adjustment = StockAdjustment::query()->firstOrFail();
        $this->assertSame($sale->id, $adjustment->pos_sale_id);
        $this->assertTrue($adjustment->record_as_sale);
    }

    public function test_all_products_can_be_set_to_one_quantity(): void
    {
        $branch = Branch::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', null, null, 10, 80);
        $toy = $this->product($branch, 'Rope toy', Brand::factory()->create(), null, 2, 30);

        $this->actingAsEmployee()
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'all',
                'record_as_sale' => false,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 0],
                    ['catalog_item_variant_id' => $toy->id, 'quantity' => 0],
                ],
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 0,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $toy->id,
            'quantity' => 0,
        ]);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_a_product_outside_the_brand_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $brand = Brand::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', $brand, null, 10, 80);
        $toy = $this->product($branch, 'Rope toy', Brand::factory()->create(), null, 2, 30);

        $this->actingAsEmployee()
            ->from('/backoffice/inventory/adjustments')
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'brand',
                'brand_id' => $brand->id,
                'record_as_sale' => false,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 9],
                    ['catalog_item_variant_id' => $toy->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect('/backoffice/inventory/adjustments')
            ->assertSessionHasErrors([
                'lines.1.catalog_item_variant_id' => 'Choose a product in this brand.',
            ]);

        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_quantity_cannot_drop_below_reserved_stock(): void
    {
        $branch = Branch::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', null, null, 10, 80);
        BranchInventory::query()->where('catalog_item_variant_id', $kibble->id)->update(['reserved' => 4]);

        $this->actingAsEmployee()
            ->from('/backoffice/inventory/adjustments')
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'all',
                'record_as_sale' => false,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect('/backoffice/inventory/adjustments')
            ->assertSessionHasErrors([
                'lines.0.quantity' => '4 units are reserved for open orders. The quantity cannot go below that.',
            ]);

        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 10,
        ]);
    }

    public function test_a_sale_requires_a_retail_price(): void
    {
        $branch = Branch::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', null, null, 10, null);

        $this->actingAsEmployee()
            ->from('/backoffice/inventory/adjustments')
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'all',
                'record_as_sale' => true,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 8],
                ],
            ])
            ->assertRedirect('/backoffice/inventory/adjustments')
            ->assertSessionHasErrors([
                'lines.0.quantity' => 'Set a retail price before recording Organic kibble as a sale.',
            ]);

        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseHas('branch_inventories', [
            'catalog_item_variant_id' => $kibble->id,
            'quantity' => 10,
        ]);
    }

    public function test_an_unchanged_quantity_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $kibble = $this->product($branch, 'Organic kibble', null, null, 10, 80);

        $this->actingAsEmployee()
            ->from('/backoffice/inventory/adjustments')
            ->post('/backoffice/inventory/adjustments', [
                'branch_id' => $branch->id,
                'scope' => 'all',
                'record_as_sale' => false,
                'lines' => [
                    ['catalog_item_variant_id' => $kibble->id, 'quantity' => 10],
                ],
            ])
            ->assertRedirect('/backoffice/inventory/adjustments')
            ->assertSessionHasErrors(['lines' => 'Change at least one quantity.']);
    }

    public function test_adjustment_requires_a_location_and_a_scope_choice(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/inventory/adjustments')
            ->post('/backoffice/inventory/adjustments', [
                'record_as_sale' => false,
                'lines' => [],
            ])
            ->assertRedirect('/backoffice/inventory/adjustments')
            ->assertSessionHasErrors([
                'branch_id' => 'Choose a location.',
                'scope' => 'Choose all products, a brand, or a category.',
                'lines' => 'Change at least one quantity.',
            ]);
    }

    private function product(Branch $branch, string $name, ?Brand $brand, ?Category $category, int $quantity, ?float $price): CatalogItemVariant
    {
        $item = CatalogItem::factory()->create([
            'name' => $name,
            'brand_id' => $brand?->id,
            'category_id' => $category?->id,
        ]);
        $variant = CatalogItemVariant::factory()->for($item)->create([
            'name' => 'Default',
            'price' => $price,
        ]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        return $variant;
    }
}
