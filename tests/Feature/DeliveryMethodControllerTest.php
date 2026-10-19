<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_delivery_method(): void
    {
        $response = $this->post('/backoffice/shop/delivery-methods', [
            'name' => 'Pickup',
            'fee' => '0',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('delivery_methods', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', []);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'Enter a delivery method name.',
        ]);
        $this->assertDatabaseCount('delivery_methods', 0);
    }

    public function test_delivery_method_is_created(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Lalamove',
            'fee' => '150',
            'notes' => 'Same-day Metro Manila.',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $response->assertSessionHas('status', 'Delivery method created.');
        $this->assertDatabaseHas('delivery_methods', [
            'name' => 'Lalamove',
            'fee' => '150.00',
            'notes' => 'Same-day Metro Manila.',
        ]);
    }

    public function test_delivery_method_can_be_created_without_a_fee(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Grab',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertNull(DeliveryMethod::query()->where('name', 'Grab')->value('fee'));
    }

    public function test_duplicate_delivery_method_name_is_rejected(): void
    {
        DeliveryMethod::factory()->create(['name' => 'Pickup']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Pickup',
            'fee' => '0',
            'is_active' => true,
        ]);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'name' => 'That delivery method already exists.',
        ]);
        $this->assertDatabaseCount('delivery_methods', 1);
    }

    public function test_delivery_method_can_be_updated(): void
    {
        $deliveryMethod = DeliveryMethod::factory()->create([
            'name' => 'Pickup',
            'fee' => 0,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->put("/backoffice/shop/delivery-methods/{$deliveryMethod->id}", [
            'name' => 'Store pickup',
            'fee' => '0',
            'notes' => 'Ready in 30 minutes.',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseHas('delivery_methods', [
            'id' => $deliveryMethod->id,
            'name' => 'Store pickup',
            'is_active' => false,
        ]);
    }

    public function test_delivery_fee_can_be_cleared_for_cart_pricing(): void
    {
        $deliveryMethod = DeliveryMethod::factory()->create([
            'name' => 'Lalamove',
            'fee' => 150,
        ]);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->put("/backoffice/shop/delivery-methods/{$deliveryMethod->id}", [
            'name' => 'Lalamove',
            'fee' => '',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertNull($deliveryMethod->fresh()->fee);
    }

    public function test_delivery_method_can_be_deleted(): void
    {
        $deliveryMethod = DeliveryMethod::factory()->create(['name' => 'Lalamove']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->delete("/backoffice/shop/delivery-methods/{$deliveryMethod->id}");

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseMissing('delivery_methods', ['id' => $deliveryMethod->id]);
    }
}
