<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;

class PaymentMethodController extends Controller
{
    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        PaymentMethod::query()->create($request->safe()->only(['name', 'instructions', 'is_cash', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method created.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->safe()->only(['name', 'instructions', 'is_cash', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method deleted.');
    }
}
