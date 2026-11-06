<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_public_home_page(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'Greenheights',
            'is_active' => true,
        ]);

        Branch::factory()->inactive()->create([
            'name' => 'Hidden Branch',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Site/Home')
            ->has('branches', 1)
            ->where('branches.0.name', $branch->name));
    }

    public function test_authenticated_employee_can_still_open_the_public_home_page(): void
    {
        $this->actingAsEmployee()->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Site/Home'),
        );
    }

    public function test_shop_without_a_banner_photo_leaves_the_banner_off_the_home_page(): void
    {
        Business::factory()->create([
            'shop_banner_path' => null,
            'shop_promo_visible' => false,
            'shop_promo_text' => 'Not shown',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('banner', null)
                ->where('promotion', null));
    }

    public function test_visible_promotional_text_is_included_on_the_home_page(): void
    {
        Business::factory()->create([
            'shop_promo_visible' => true,
            'shop_promo_text' => 'Free delivery this week',
            'shop_promo_background' => '#053b63',
            'shop_promo_color' => '#ffffff',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('promotion.text', 'Free delivery this week')
                ->where('promotion.background_color', '#053b63')
                ->where('promotion.text_color', '#ffffff'));
    }

    public function test_visible_shop_banner_is_included_on_the_home_page(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('banner.jpg')->store('shop-banners', 'public');
        Business::factory()->create([
            'shop_banner_visible' => true,
            'shop_banner_path' => $path,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Site/Home')
                ->where('banner.image_url', fn (string $url): bool => str_ends_with($url, basename($path)))
                ->missing('banner.message'));
    }

    public function test_guest_is_not_redirected_from_the_home_page_to_login(): void
    {
        $this->get('/')->assertOk();
        $this->get('/backoffice')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Auth/Login'),
        );
    }
}
