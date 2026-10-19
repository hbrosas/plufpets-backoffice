<?php

namespace Tests\Feature;

use App\Enums\TaxFeeType;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\TaxFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/backoffice/shop/settings')->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_settings(): void
    {
        TaxFee::factory()->create([
            'name' => 'VAT',
            'type' => TaxFeeType::Percent,
            'rate' => 12,
        ]);
        PaymentMethod::factory()->create(['name' => 'GCash']);
        DeliveryMethod::factory()->create([
            'name' => 'Lalamove',
            'fee' => 150,
        ]);
        DeliveryMethod::factory()->pricedInCart()->create(['name' => 'Grab']);

        $response = $this->actingAsEmployee()->get('/backoffice/shop/settings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Settings/Index')
            ->where('taxFees.0.name', 'VAT')
            ->where('taxFees.0.amount_label', '12.00%')
            ->where('paymentMethods.0.name', 'GCash')
            ->where('deliveryMethods.0.name', 'Grab')
            ->where('deliveryMethods.0.fee_label', 'Set in cart')
            ->where('deliveryMethods.1.name', 'Lalamove')
            ->where('deliveryMethods.1.fee_label', '₱150.00'));
    }
}
