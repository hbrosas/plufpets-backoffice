<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxFeeRequest;
use App\Http\Requests\UpdateTaxFeeRequest;
use App\Models\TaxFee;
use Illuminate\Http\RedirectResponse;

class TaxFeeController extends Controller
{
    public function store(StoreTaxFeeRequest $request): RedirectResponse
    {
        TaxFee::query()->create($request->safe()->only(['name', 'type', 'rate', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Tax or fee created.');
    }

    public function update(UpdateTaxFeeRequest $request, TaxFee $taxFee): RedirectResponse
    {
        $taxFee->update($request->safe()->only(['name', 'type', 'rate', 'is_active']));

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Tax or fee updated.');
    }

    public function destroy(TaxFee $taxFee): RedirectResponse
    {
        $taxFee->delete();

        return redirect()
            ->route('shop.settings.index')
            ->with('status', 'Tax or fee deleted.');
    }
}
