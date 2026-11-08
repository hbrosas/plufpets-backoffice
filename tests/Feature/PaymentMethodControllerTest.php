<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'asks_for_change' => false,
        ]);
    }

    public function test_payment_method_can_ask_for_change(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'Cash on delivery',
            'is_cash' => true,
            'asks_for_change' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Cash on delivery',
            'is_cash' => true,
            'asks_for_change' => true,
        ]);
    }

    public function test_payment_method_can_be_deleted(): void
    {
        $paymentMethod = PaymentMethod::factory()->create(['name' => 'GCash']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->delete("/backoffice/shop/payment-methods/{$paymentMethod->id}");

        $response->assertRedirect(route('shop.settings.index'));
        $this->assertDatabaseMissing('payment_methods', ['id' => $paymentMethod->id]);
    }

    public function test_payment_instructions_can_differ_by_branch(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights']);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'instructions' => 'Shared QR',
            'instructions_for_all_branches' => false,
            'branch_instructions' => [
                ['branch_id' => $greenheights->id, 'instructions' => 'Scan the Greenheights QR.'],
                ['branch_id' => $sanPedro->id, 'instructions' => 'Scan the San Pedro QR.'],
            ],
            'is_cash' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $method = PaymentMethod::query()->where('name', 'GCash')->first();
        $this->assertFalse($method->instructions_for_all_branches);
        $this->assertSame('Scan the Greenheights QR.', $method->instructionFor($greenheights->id));
        $this->assertSame('Scan the San Pedro QR.', $method->instructionFor($sanPedro->id));
        $this->assertSame('Shared QR', $method->instructionFor(null));
    }

    public function test_payment_instruction_photos_can_differ_by_branch(): void
    {
        Storage::fake('public');
        $branch = Branch::factory()->create(['name' => 'Greenheights']);

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'instructions_for_all_branches' => false,
            'branch_instructions' => [
                [
                    'branch_id' => $branch->id,
                    'instructions' => 'Scan the branch QR.',
                    'images' => [UploadedFile::fake()->image('branch.jpg')],
                ],
            ],
            'is_cash' => false,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('shop.settings.index'));
        $method = PaymentMethod::query()->where('name', 'GCash')->first();
        $this->assertCount(1, $method->instructionImageUrls($branch->id));
        Storage::disk('public')->assertExists($method->branchInstructions->first()->images[0]);
    }

    public function test_payment_instruction_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $paymentMethod = PaymentMethod::factory()->create([
            'name' => 'GCash',
            'is_cash' => false,
            'is_active' => true,
        ]);

        $this->actingAsEmployee()->put("/backoffice/shop/payment-methods/{$paymentMethod->id}", [
            'name' => 'GCash',
            'is_cash' => false,
            'is_active' => true,
            'images' => [UploadedFile::fake()->image('qr.jpg')],
        ])->assertRedirect();

        $path = $paymentMethod->fresh()->instruction_images[0];
        Storage::disk('public')->assertExists($path);

        $this->actingAsEmployee()->from('/backoffice/shop/settings')->put("/backoffice/shop/payment-methods/{$paymentMethod->id}", [
            'name' => 'GCash',
            'is_cash' => false,
            'is_active' => true,
            'remove_images' => [$path],
        ])->assertRedirect();

        $this->assertSame([], $paymentMethod->fresh()->instruction_images ?? []);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_more_than_four_payment_photos_are_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()->from('/backoffice/shop/settings')->post('/backoffice/shop/payment-methods', [
            'name' => 'GCash',
            'is_cash' => false,
            'is_active' => true,
            'images' => [
                UploadedFile::fake()->image('1.jpg'),
                UploadedFile::fake()->image('2.jpg'),
                UploadedFile::fake()->image('3.jpg'),
                UploadedFile::fake()->image('4.jpg'),
                UploadedFile::fake()->image('5.jpg'),
            ],
        ]);

        $response->assertRedirect('/backoffice/shop/settings');
        $response->assertSessionHasErrors([
            'images' => 'You can add up to 4 photos.',
        ]);
        $this->assertDatabaseCount('payment_methods', 0);
    }
}
