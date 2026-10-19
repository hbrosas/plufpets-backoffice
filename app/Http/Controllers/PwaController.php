<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $name = (string) config('app.name');

        return response()->json([
            'id' => '/backoffice',
            'name' => $name,
            'short_name' => 'Pluf Pets',
            'description' => 'Pluf Pets — shop, grooming, and hotel for dogs and cats.',
            'start_url' => '/backoffice',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#1f6f68',
            'theme_color' => '#1f6f68',
            'prefer_related_applications' => false,
            'icons' => [
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES)->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        return response(File::get(resource_path('pwa/sw.js')), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): View
    {
        return view('pwa.offline', [
            'appName' => (string) config('app.name'),
        ]);
    }
}
