<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelPodTypeRequest;
use App\Http\Requests\UpdateHotelPodTypeRequest;
use App\Models\HotelPodType;
use Illuminate\Http\RedirectResponse;

class HotelPodTypeController extends Controller
{
    public function store(StoreHotelPodTypeRequest $request): RedirectResponse
    {
        $maxOrder = (int) HotelPodType::query()->max('sort_order');

        HotelPodType::query()->create([
            ...$request->safe()->only(['name', 'is_active']),
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Pod type created.');
    }

    public function update(UpdateHotelPodTypeRequest $request, HotelPodType $hotelPodType): RedirectResponse
    {
        $hotelPodType->update($request->safe()->only(['name', 'is_active']));

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Pod type updated.');
    }

    public function destroy(HotelPodType $hotelPodType): RedirectResponse
    {
        if ($hotelPodType->stays()->exists()) {
            return back()->withErrors([
                'pod_type' => 'This pod type is in use.',
            ]);
        }

        $hotelPodType->delete();

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Pod type deleted.');
    }
}
