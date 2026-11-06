<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopPromotionRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShopPromotionController extends Controller
{
    public function index(): Response
    {
        $business = Business::current();

        return Inertia::render('Shop/Promotion/Index', [
            'promotion' => [
                'visible' => (bool) $business?->shop_promo_visible,
                'text' => $business?->shop_promo_text ?? '',
                'background_color' => $this->color($business?->shop_promo_background, '#053b63'),
                'text_color' => $this->color($business?->shop_promo_color, '#ffffff'),
            ],
        ]);
    }

    public function update(UpdateShopPromotionRequest $request): RedirectResponse
    {
        $business = Business::current() ?? new Business([
            'name' => Business::documentName(),
        ]);
        $business->shop_promo_visible = $request->boolean('visible');
        $business->shop_promo_text = $request->validated('text');
        $business->shop_promo_background = $request->validated('background_color');
        $business->shop_promo_color = $request->validated('text_color');
        $business->save();

        return back()->with('status', 'Promotional text saved.');
    }

    private function color(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtolower($value)
            : $fallback;
    }
}
