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

class SalesOverviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_sales(): void
    {
        $response = $this->get('/backoffice/sales');

        $response->assertRedirect(route('login'));
    }

    public function test_overview_totals_today_sales_and_excludes_other_days(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);
        $this->recordSale($branch, $shift, [
            'total' => 112,
            'tax_total' => 12,
            'sold_at' => now(),
        ], [
            'payment_method_name' => 'GCash',
            'amount' => 112,
            'is_cash' => false,
        ]);
        $this->recordSale($branch, $shift, [
            'total' => 50,
            'tax_total' => 0,
            'sold_at' => now()->subDay(),
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Overview')
            ->where('summary.receipts', 1)
            ->where('summary.total', '₱112.00')
            ->where('summary.tax', '₱12.00')
            ->where('summary.average', '₱112.00')
            ->where('by_branch.0.name', 'Greenheights')
            ->where('by_branch.0.receipts', 1)
            ->where('by_branch.0.total', '₱112.00')
            ->where('by_payment.0.name', 'GCash')
            ->where('by_payment.0.total', '₱112.00'));
    }

    public function test_overview_branch_filter_excludes_other_branches(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights']);
        $makati = Branch::factory()->create(['name' => 'Makati']);
        $this->recordSale($greenheights, PosShift::factory()->create(['branch_id' => $greenheights->id]), [
            'total' => 200,
            'sold_at' => now(),
        ]);
        $this->recordSale($makati, PosShift::factory()->create(['branch_id' => $makati->id]), [
            'total' => 80,
            'sold_at' => now(),
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales?branch_id='.$greenheights->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('summary.receipts', 1)
            ->where('summary.total', '₱200.00')
            ->where('by_branch.0.name', 'Greenheights')
            ->has('by_branch', 1));
    }

    /**
     * @param  array<string, mixed>  $sale
     * @param  array<string, mixed>  $payment
     */
    private function recordSale(Branch $branch, PosShift $shift, array $sale = [], array $payment = []): PosSale
    {
        $recorded = PosSale::factory()->create([
            'pos_shift_id' => $shift->id,
            'branch_id' => $branch->id,
            'subtotal' => $sale['total'] ?? 100,
            'tax_total' => 0,
            'total' => 100,
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
            ...$payment,
        ]);

        return $recorded;
    }
}
