<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DeliveryMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_delivery_instructions_can_differ_by_branch(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights']);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Lalamove',
            'fee' => '150',
            'notes' => 'One instruction for every branch.',
            'instructions_for_all_branches' => false,
            'branch_instructions' => [
                ['branch_id' => $greenheights->id, 'instructions' => 'Rider will call the Greenheights line.'],
                ['branch_id' => $sanPedro->id, 'instructions' => 'Meet the rider at San Pedro.'],
            ],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $method = DeliveryMethod::query()->where('name', 'Lalamove')->first();
        $this->assertFalse($method->instructions_for_all_branches);
        $this->assertSame('Rider will call the Greenheights line.', $method->instructionFor($greenheights->id));
        $this->assertSame('Meet the rider at San Pedro.', $method->instructionFor($sanPedro->id));
    }

    public function test_delivery_instruction_photos_are_saved(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Lalamove',
            'fee' => '80',
            'notes' => 'Show this photo to the rider.',
            'is_active' => true,
            'images' => [UploadedFile::fake()->image('rider.jpg')],
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $method = DeliveryMethod::query()->where('name', 'Lalamove')->first();
        $this->assertCount(1, $method->instructionImageUrls(null));
        Storage::disk('public')->assertExists($method->instruction_images[0]);
    }

    public function test_a_delivery_method_can_use_a_delivery_schedule(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/delivery-methods', [
            'name' => 'Rider',
            'fee' => '0',
            'uses_delivery_schedule' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $method = DeliveryMethod::query()->where('name', 'Rider')->first();
        $this->assertTrue($method->uses_delivery_schedule);
    }
}
