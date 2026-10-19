<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_payment_method(): void
    {
        $response = $this->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('payment_methods', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', []);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'Enter a payment method name.',
        ]);
        $this->assertDatabaseCount('payment_methods', 0);
    }

    public function test_payment_method_is_created(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'instructions' => 'Scan the QR at the counter.',
            'is_cash' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $response->assertSessionHas('status', 'Payment method created.');
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'GCash',
            'instructions' => 'Scan the QR at the counter.',
            'is_cash' => false,
        ]);
    }

    public function test_duplicate_payment_method_name_is_rejected(): void
    {
        PaymentMethod::factory()->create(['name' => 'Cash']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'Cash',
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'That payment method already exists.',
        ]);
        $this->assertDatabaseCount('payment_methods', 1);
    }

    public function test_payment_method_can_be_updated(): void
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'Cash',
            'is_active' => true,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->put("/backoffice/shop/payment-methods/{$paymentMethod->id}", [
            'name' => 'Cash on delivery',
            'instructions' => 'Pay the rider.',
            'is_cash' => true,
            'is_active' => false,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'name' => 'Cash on delivery',
            'instructions' => 'Pay the rider.',
            'is_cash' => true,
            'is_active' => false,
        ]);
    }

    public function test_payment_method_can_be_deleted(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['name' => 'GCash']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->delete("/backoffice/shop/payment-methods/{$paymentMethod->id}");

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseMissing('payment_methods', ['id' => $paymentMethod->id]);
    }
}
