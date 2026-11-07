<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Support\BranchInstructions;
use App\Support\InstructionImages;
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
        InstructionImages::storeShared($paymentMethod, $request->file('images'), $request->input('remove_images'));

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
        InstructionImages::storeShared($paymentMethod, $request->file('images'), $request->input('remove_images'));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        InstructionImages::deleteStored($paymentMethod);
        $paymentMethod->delete();

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Payment method deleted.');
    }
}
