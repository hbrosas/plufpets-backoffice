<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockConversionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_stock_conversion(): void
    {
        $response = $this->post('/backoffice/inventory/conversions', []);

        $response->assertRedirect(route('login'));
    }

    public function test_stock_converts_bulk_units_into_pieces_of_another_product(): void
    {
        $branch = Branch::factory()->create(['name' => 'Makati Branch']);
        $sack = CatalogItem::factory()->create(['name' => 'Sack of Dry Food']);
        $repack = CatalogItem::factory()->create(['name' => 'Repacked Dry Food']);
        $sackVariant = CatalogItemVariant::factory()->for($sack)->create(['name' => '7kg', 'price' => 700]);
        $repackVariant = CatalogItemVariant::factory()->for($repack)->create(['name' => '1kg', 'price' => 120]);
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $sackVariant->id,
            'quantity' => 2,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->create()->id,
            'catalog_item_variant_id' => $sackVariant->id,
            'unit_cost' => 700,
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/inventory/conversions', [
            'branch_id' => $branch->id,
            'from_catalog_item_variant_id' => $sackVariant->id,
            'to_catalog_item_variant_id' => $repackVariant->id,
            'quantity' => 1,
            'pieces_per_unit' => 7,
        ]);

        $response->assertRedirect(route('inventory.index'));
        $response->assertSessionHas('status', 'Stock converted.');
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $sackVariant->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $repackVariant->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('stock_conversions', [
            'branch_id' => $branch->id,
            'from_catalog_item_variant_id' => $sackVariant->id,
            'to_catalog_item_variant_id' => $repackVariant->id,
            'from_quantity' => 1,
            'to_quantity' => 7,
            'unit_cost' => 100,
        ]);

        $inventory = $this->actingAsEmployee()->get('/backoffice/inventory');

        $inventory->assertOk();
        $inventory->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Index')
            ->where('rows.0.product', 'Repacked Dry Food')
            ->where('rows.0.purchase_price', '₱100.00')
            ->where('rows.0.purchase_price_amount', 100)
            ->where('rows.1.product', 'Sack of Dry Food')
            ->where('rows.1.purchase_price', '₱700.00'));
    }

    public function test_conversion_is_rejected_when_source_stock_is_insufficient(): void
    {
        $branch = Branch::factory()->create();
        $from = CatalogItemVariant::factory()->create();
        $to = CatalogItemVariant::factory()->create();
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $from->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->post('/backoffice/inventory/conversions', [
                'branch_id' => $branch->id,
                'from_catalog_item_variant_id' => $from->id,
                'to_catalog_item_variant_id' => $to->id,
                'quantity' => 5,
                'pieces_per_unit' => 7,
            ]);

        $response->assertRedirect('/backoffice/inventory');
        $response->assertSessionHasErrors(['quantity' => 'Only 1 units are available at that location.']);
        $this->assertDatabaseCount('stock_conversions', 0);
    }

    public function test_conversion_cannot_target_the_same_product(): void
    {
        $branch = Branch::factory()->create();
        $variant = CatalogItemVariant::factory()->create();
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->post('/backoffice/inventory/conversions', [
                'branch_id' => $branch->id,
                'from_catalog_item_variant_id' => $variant->id,
                'to_catalog_item_variant_id' => $variant->id,
                'quantity' => 1,
                'pieces_per_unit' => 2,
            ]);

        $response->assertRedirect('/backoffice/inventory');
        $response->assertSessionHasErrors(['from_catalog_item_variant_id' => 'Choose a different product to convert into.']);
        $this->assertDatabaseCount('stock_conversions', 0);
    }

    public function test_conversion_cannot_target_a_service(): void
    {
        $branch = Branch::factory()->create();
        $from = CatalogItemVariant::factory()->create();
        $service = CatalogItem::factory()->service()->create();
        $to = CatalogItemVariant::factory()->for($service)->create();
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $from->id,
            'quantity' => 4,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->post('/backoffice/inventory/conversions', [
                'branch_id' => $branch->id,
                'from_catalog_item_variant_id' => $from->id,
                'to_catalog_item_variant_id' => $to->id,
                'quantity' => 1,
                'pieces_per_unit' => 2,
            ]);

        $response->assertRedirect('/backoffice/inventory');
        $response->assertSessionHasErrors(['to_catalog_item_variant_id' => 'Choose a product to convert into.']);
        $this->assertDatabaseCount('stock_conversions', 0);
    }

    public function test_conversion_requires_location_products_and_quantities(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->post('/backoffice/inventory/conversions', []);

        $response->assertRedirect('/backoffice/inventory');
        $response->assertSessionHasErrors([
            'branch_id' => 'Choose a location.',
            'from_catalog_item_variant_id' => 'Choose the product to convert.',
            'to_catalog_item_variant_id' => 'Choose the product to convert into.',
            'quantity' => 'Enter a quantity.',
            'pieces_per_unit' => 'Enter how many pieces each unit becomes.',
        ]);
    }
}
