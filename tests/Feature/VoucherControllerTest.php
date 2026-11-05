<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_voucher(): void
    {
        $response = $this->post('/backoffice/shop/vouchers', [
            'code' => 'SAVE10',
            'type' => 'fixed',
            'amount' => '50',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_voucher_is_created_and_updated(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/vouchers', [
            'code' => 'save10',
            'type' => 'percent',
            'amount' => '10',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $voucher = Voucher::query()->first();
        $this->assertNotNull($voucher);
        $this->assertSame('SAVE10', $voucher->code);
        $this->assertSame('percent', $voucher->type->value);

        $response = $this->actingAsEmployee()->put('/backoffice/shop/vouchers/'.$voucher->id, [
            'code' => 'SAVE10',
            'type' => 'fixed',
            'amount' => '25',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'amount' => '25.00',
            'is_active' => false,
        ]);
    }
}
