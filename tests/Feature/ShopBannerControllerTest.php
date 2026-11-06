<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopBannerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_banner_page(): void
    {
        $this->get('/backoffice/shop/banner')->assertRedirect(route('login'));
        $this->post('/backoffice/shop/banner')->assertRedirect(route('login'));
    }

    public function test_banner_with_the_wrong_size_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/banner')
            ->post('/backoffice/shop/banner', [
                'image' => UploadedFile::fake()->image('banner.jpg', 800, 250),
            ]);

        $response->assertRedirect('/backoffice/shop/banner');
        $response->assertSessionHasErrors([
            'image' => 'The banner must be 1600 by 500 pixels.',
        ]);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_non_image_banner_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/banner')
            ->post('/backoffice/shop/banner', [
                'image' => UploadedFile::fake()->create('banner.pdf', 20, 'application/pdf'),
            ]);

        $response->assertRedirect('/backoffice/shop/banner');
        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_banner_image_can_be_uploaded(): void
    {
        Storage::fake('public');

        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/banner')
            ->post('/backoffice/shop/banner', [
                'image' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
            ]);

        $response->assertRedirect('/backoffice/shop/banner');
        $response->assertSessionHas('status', 'Banner saved.');

        $business = Business::query()->first();
        $this->assertNotNull($business);
        Storage::disk('public')->assertExists($business->shop_banner_path);

        $this->actingAsEmployee()
            ->get('/backoffice/shop/banner')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shop/Banner/Index')
                ->where('banner.image_url', fn (string $url): bool => str_ends_with($url, basename((string) $business->shop_banner_path))));
    }

    public function test_removing_the_banner_image_hides_it(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('banner.jpg', 1600, 500)->store('shop-banners', 'public');
        $business = Business::factory()->create([
            'shop_banner_path' => $path,
        ]);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/banner')
            ->post('/backoffice/shop/banner', [
                'remove_image' => true,
            ]);

        $response->assertRedirect('/backoffice/shop/banner');
        $business->refresh();
        $this->assertNull($business->shop_banner_path);
        Storage::disk('public')->assertMissing($path);
    }
}
