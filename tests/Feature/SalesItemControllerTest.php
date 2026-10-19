<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_sold_items(): void
    {
        $response = $this->get('/backoffice/sales/items');

        $response->assertRedirect(route('login'));
    }

    public function test_sold_items_are_grouped_by_product_and_variant(): void
    {
        $branch = Branch::factory()->create();
        $sale = PosSale::factory()->create([
            'pos_shift_id' => PosShift::factory()->create(['branch_id' => $branch->id]),
            'branch_id' => $branch->id,
            'sold_at' => now(),
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'product_name' => 'The Full Pluf',
            'variant_name' => 'Small',
            'quantity' => 2,
            'line_total' => 1100,
        ]);
        PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
            'product_name' => 'The Full Pluf',
            'variant_name' => 'Small',
            'quantity' => 1,
            'line_total' => 550,
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/items');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Items/Index')
            ->has('items', 1)
            ->where('items.0.product', 'The Full Pluf')
            ->where('items.0.variant', 'Small')
            ->where('items.0.quantity', 3)
            ->where('items.0.total', '₱1,650.00'));
    }
}
