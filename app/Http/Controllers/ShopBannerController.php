<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopBannerRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ShopBannerController extends Controller
{
    public function index(): Response
    {
        $business = Business::current();

        return Inertia::render('Shop/Banner/Index', [
            'banner' => [
                'image_url' => $business?->shopBannerUrl(),
            ],
        ]);
    }

    public function update(UpdateShopBannerRequest $request): RedirectResponse
    {
        $business = Business::current() ?? new Business([
            'name' => Business::documentName(),
        ]);
        $this->syncBannerImage($business, $request->file('image'), $request->boolean('remove_image'));
        $business->shop_banner_visible = filled($business->shop_banner_path);
        $business->save();

        return back()->with('status', 'Banner saved.');
    }

    private function syncBannerImage(Business $business, ?UploadedFile $file, bool $remove): void
    {
        if ($file instanceof UploadedFile) {
            if (filled($business->shop_banner_path)) {
                Storage::disk('public')->delete($business->shop_banner_path);
            }

            $business->shop_banner_path = $file->store('shop-banners', 'public');

            return;
        }

        if ($remove && filled($business->shop_banner_path)) {
            Storage::disk('public')->delete($business->shop_banner_path);
            $business->shop_banner_path = null;
        }
    }
}
