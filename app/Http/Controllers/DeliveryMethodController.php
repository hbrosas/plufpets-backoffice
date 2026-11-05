<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryMethodRequest;
use App\Http\Requests\UpdateDeliveryMethodRequest;
use App\Models\DeliveryMethod;
use App\Support\BranchInstructions;
use Illuminate\Http\RedirectResponse;

class DeliveryMethodController extends Controller
{
    public function store(StoreDeliveryMethodRequest $request): RedirectResponse
    {
        $deliveryMethod = DeliveryMethod::query()->create($request->safe()->only([
            'name',
            'fee',
            'notes',
            'instructions_for_all_branches',
            'asks_for_address',
            'is_active',
        ]));
        BranchInstructions::sync($deliveryMethod, $request->validated('branch_instructions') ?? []);

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Delivery method created.');
    }

    public function update(UpdateDeliveryMethodRequest $request, DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $deliveryMethod->update($request->safe()->only([
            'name',
            'fee',
            'notes',
            'instructions_for_all_branches',
            'asks_for_address',
            'is_active',
        ]));
        BranchInstructions::sync($deliveryMethod, $request->validated('branch_instructions') ?? []);

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Delivery method updated.');
    }

    public function destroy(DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $deliveryMethod->delete();

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Delivery method deleted.');
    }
}
