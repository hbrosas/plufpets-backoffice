<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesReceiptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_receipts(): void
    {
        $response = $this->get('/backoffice/sales/receipts');

        $response->assertRedirect(route('login'));
    }

    public function test_receipts_list_today_sales_and_can_search_by_number(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $visible = $this->recordSale($branch, $shift, ['number' => 'POS-KEEP', 'sold_at' => now()]);
        $this->recordSale($branch, $shift, ['number' => 'POS-HIDE', 'sold_at' => now()]);
        $this->recordSale($branch, $shift, ['number' => 'POS-OLD', 'sold_at' => now()->subDay()]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts?search=KEEP');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receipts/Index')
            ->has('receipts.data', 1)
            ->where('receipts.data.0.number', 'POS-KEEP')
            ->where('receipts.data.0.branch', 'Greenheights')
            ->where('receipts.data.0.is_refunded', false)
            ->where('receipts.data.0.total', '₱'.$this->peso($visible->total)));
    }

    public function test_receipt_show_includes_items_and_payments(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $sale = $this->recordSale(
            $branch,
            PosShift::factory()->create(['branch_id' => $branch->id]),
            ['number' => 'POS-SHOW', 'sold_at' => now()],
        );

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts/'.$sale->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Receipts/Show')
            ->where('receipt.number', 'POS-SHOW')
            ->where('receipt.branch', 'Greenheights')
            ->where('receipt.items.0.product', 'The Full Pluf')
            ->where('receipt.payments.0.name', 'Cash'));
    }

    /**
     * @param  array<string, mixed>  $sale
     */
    private function recordSale(Branch $branch, PosShift $shift, array $sale = []): PosSale
    {
        $recorded = PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'total' => 112,
            ...$sale,
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $recorded->id,
            'product_name' => 'The Full Pluf',
            'variant_name' => 'Small',
            'quantity' => 1,
            'line_total' => $recorded->total,
        ]);
        PosSalePayment::factory()->create([
            'pos_sale_id' => $recorded->id,
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'amount' => $recorded->total,
        ]);

        return $recorded;
    }

    private function peso(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
