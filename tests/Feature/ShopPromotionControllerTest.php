<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopPromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_promotional_text(): void
    {
        $this->get('/backoffice/shop/promotion')->assertRedirect(route('login'));
        $this->post('/backoffice/shop/promotion')->assertRedirect(route('login'));
    }

    public function test_visible_promotional_text_requires_text(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/promotion')
            ->post('/backoffice/shop/promotion', [
                'visible' => true,
                'text' => '   ',
                'background_color' => '#053b63',
                'text_color' => '#ffffff',
            ]);

        $response->assertRedirect('/backoffice/shop/promotion');
        $response->assertSessionHasErrors([
            'text' => 'Enter the promotional text.',
        ]);
        $this->assertDatabaseCount('businesses', 0);
    }

    public function test_promotional_text_rejects_an_invalid_color(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/promotion')
            ->post('/backoffice/shop/promotion', [
                'visible' => true,
                'text' => 'Free delivery this week',
                'background_color' => 'navy',
                'text_color' => '#ffffff',
            ]);

        $response->assertRedirect('/backoffice/shop/promotion');
        $response->assertSessionHasErrors([
            'background_color' => 'Choose a background color.',
        ]);
    }

    public function test_promotional_text_can_be_saved(): void
    {
        $response = $this->actingAsEmployee()
            ->from('/backoffice/shop/promotion')
            ->post('/backoffice/shop/promotion', [
                'visible' => true,
                'text' => '  Free delivery this week  ',
                'background_color' => '#053B63',
                'text_color' => '#FFFFFF',
            ]);

        $response->assertRedirect('/backoffice/shop/promotion');
        $response->assertSessionHas('status', 'Promotional text saved.');

        $business = Business::query()->first();
        $this->assertNotNull($business);
        $this->assertTrue($business->shop_promo_visible);
        $this->assertSame('Free delivery this week', $business->shop_promo_text);
        $this->assertSame('#053b63', $business->shop_promo_background);
        $this->assertSame('#ffffff', $business->shop_promo_color);

        $this->actingAsEmployee()
            ->get('/backoffice/shop/promotion')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shop/Promotion/Index')
                ->where('promotion.visible', true)
                ->where('promotion.text', 'Free delivery this week')
                ->where('promotion.background_color', '#053b63')
                ->where('promotion.text_color', '#ffffff'));
    }
}
