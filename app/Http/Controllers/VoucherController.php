<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;

class VoucherController extends Controller
{
    public function store(StoreVoucherRequest $request): RedirectResponse
    {
        Voucher::query()->create($request->safe()->only(['code', 'type', 'amount', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Voucher created.');
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $voucher->update($request->safe()->only(['code', 'type', 'amount', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Voucher updated.');
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Voucher deleted.');
    }
}
