<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_an_installable_web_app_manifest(): void
    {
        config(['app.name' => 'Harbor Pets']);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/manifest+json');
        $response->assertHeaderMissing('set-cookie');
        $response->assertJsonPath('name', 'Harbor Pets');
        $response->assertJsonPath('short_name', 'Pluf Pets');
        $response->assertJsonPath('id', '/backoffice');
        $response->assertJsonPath('start_url', '/backoffice');
        $response->assertJsonPath('scope', '/');
        $response->assertJsonPath('display', 'standalone');
        $response->assertJsonPath('prefer_related_applications', false);
        $response->assertJsonPath('icons.0.sizes', '192x192');
        $response->assertJsonPath('icons.0.purpose', 'any');
        $response->assertJsonPath('icons.1.src', '/icons/icon-512.png');
        $response->assertJsonPath('icons.1.purpose', 'any');
        $response->assertJsonPath('icons.2.src', '/icons/icon-maskable-512.png');
        $response->assertJsonPath('icons.2.purpose', 'maskable');

        $icon192 = getimagesize(public_path('icons/icon-192.png'));
        $icon512 = getimagesize(public_path('icons/icon-512.png'));
        $maskableIcon = getimagesize(public_path('icons/icon-maskable-512.png'));
        $appleIcon = getimagesize(public_path('icons/apple-touch-icon.png'));

        $this->assertSame(192, $icon192[0]);
        $this->assertSame(192, $icon192[1]);
        $this->assertSame(512, $icon512[0]);
        $this->assertSame(512, $icon512[1]);
        $this->assertSame(512, $maskableIcon[0]);
        $this->assertSame(512, $maskableIcon[1]);
        $this->assertSame(180, $appleIcon[0]);
        $this->assertSame(180, $appleIcon[1]);
    }

    public function test_service_worker_is_served_for_installation(): void
    {
        $response = $this->get('/sw.js');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/javascript; charset=UTF-8');
        $response->assertHeaderMissing('set-cookie');
        $response->assertSee("addEventListener('fetch'", false);
        $response->assertSee("caches.match('/offline')", false);
        $response->assertDontSee('<html', false);
    }

    public function test_offline_page_is_available_without_signing_in(): void
    {
        config(['app.name' => 'Harbor Pets']);

        $response = $this->get('/offline');

        $response->assertOk();
        $response->assertHeaderMissing('set-cookie');
        $response->assertSee("You're offline", false);
        $response->assertSee('Harbor Pets', false);
        $response->assertSee('Try again', false);
    }

    public function test_sign_in_page_links_the_web_app_manifest(): void
    {
        $response = $this->get('/backoffice');

        $response->assertOk();
        $response->assertSee('href="/manifest.webmanifest"', false);
        $response->assertSee('name="theme-color" content="#1f6f68"', false);
        $response->assertSee('href="/icons/apple-touch-icon.png"', false);
    }
}
