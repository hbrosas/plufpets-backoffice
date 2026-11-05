<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Support\BranchInstructions;
use Illuminate\Http\RedirectResponse;

class PaymentMethodController extends Controller
{
    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $paymentMethod = PaymentMethod::query()->create($request->safe()->only([
            'name',
            'instructions',
            'instructions_for_all_branches',
            'fee',
            'is_cash',
            'is_active',
        ]));
        BranchInstructions::sync($paymentMethod, $request->validated('branch_instructions') ?? []);

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method created.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->safe()->only([
            'name',
            'instructions',
            'instructions_for_all_branches',
            'fee',
            'is_cash',
            'is_active',
        ]));
        BranchInstructions::sync($paymentMethod, $request->validated('branch_instructions') ?? []);

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
