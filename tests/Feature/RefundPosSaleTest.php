<?php

namespace Tests\Feature;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPosSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_refund_a_receipt(): void
    {
        $sale = PosSale::factory()->create();

        $response = $this->post('/backoffice/sales/receipts/'.$sale->id.'/refund', [
            'reason' => 'Customer returned the item',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertNull($sale->fresh()->refunded_at);
    }

    public function test_refund_restores_product_stock_and_marks_the_receipt(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $variant = CatalogItemVariant::factory()
            ->for(CatalogItem::factory()->create(['type' => CatalogItemType::Product]))
            ->create();
        BranchInventory::factory()->create([
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
        $sale = PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'number' => 'POS-REFUND',
            'total' => 100,
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 3,
            'line_total' => 100,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/sales/receipts/'.$sale->id)
            ->post('/backoffice/sales/receipts/'.$sale->id.'/refund', [
                'reason' => 'Customer returned the item',
            ]);

        $response->assertRedirect('/backoffice/sales/receipts/'.$sale->id);
        $response->assertSessionHas('status', 'Receipt POS-REFUND refunded.');
        $this->assertNotNull($sale->fresh()->refunded_at);
        $this->assertSame('Customer returned the item', $sale->fresh()->refund_reason);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
    }

    public function test_refund_is_rejected_without_a_reason(): void
    {
        $sale = PosSale::factory()->create();

        $response = $this->actingAsEmployee()
            ->from('/backoffice/sales/receipts/'.$sale->id)
            ->post('/backoffice/sales/receipts/'.$sale->id.'/refund', [
                'reason' => '',
            ]);

        $response->assertRedirect('/backoffice/sales/receipts/'.$sale->id);
        $response->assertSessionHasErrors(['reason' => 'Enter a reason for the refund.']);
        $this->assertNull($sale->fresh()->refunded_at);
    }

    public function test_already_refunded_receipt_cannot_be_refunded_again(): void
    {
        $sale = PosSale::factory()->create([
            'refunded_at' => now(),
            'refunded_by_name' => 'Sample Employee',
            'refund_reason' => 'Already done',
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/sales/receipts/'.$sale->id)
            ->post('/backoffice/sales/receipts/'.$sale->id.'/refund', [
                'reason' => 'Try again',
            ]);

        $response->assertRedirect('/backoffice/sales/receipts/'.$sale->id);
        $response->assertSessionHasErrors(['status' => 'This receipt has already been refunded.']);
    }
}
