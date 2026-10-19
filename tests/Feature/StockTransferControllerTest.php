<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfers_between_branches(): void
    {
        $from = Branch::factory()->create(['name' => 'Makati Branch']);
        $to = Branch::factory()->create(['name' => 'BGC Branch']);
        $item = CatalogItem::factory()->create();
        $variant = CatalogItemVariant::factory()->for($item)->create();
        BranchInventory::factory()->create([
            'branch_id' => $from->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAsEmployee()->post('/backoffice/inventory/transfers', [
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $from->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 6,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $to->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('stock_transfers', [
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'quantity' => 4,
        ]);
    }

    public function test_transfer_is_rejected_when_source_stock_is_insufficient(): void
    {
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();
        $item = CatalogItem::factory()->create();
        $variant = CatalogItemVariant::factory()->for($item)->create();
        BranchInventory::factory()->create([
            'branch_id' => $from->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory')
            ->post('/backoffice/inventory/transfers', [
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'catalog_item_variant_id' => $variant->id,
                'quantity' => 5,
            ]);

        $response->assertRedirect('/backoffice/inventory');
        $response->assertSessionHasErrors(['quantity']);
    }
}
