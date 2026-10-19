<?php

namespace App\Http\Controllers;

use App\Actions\ConvertStock;
use App\Http\Requests\StoreStockConversionRequest;
use Illuminate\Http\RedirectResponse;

class StockConversionController extends Controller
{
    public function store(StoreStockConversionRequest $request, ConvertStock $convertStock): RedirectResponse
    {
        $convertStock->handle($request->safe()->only([
            'branch_id',
            'from_catalog_item_variant_id',
            'to_catalog_item_variant_id',
            'quantity',
            'pieces_per_unit',
            'notes',
        ]));

        return redirect()
            ->route('inventory.index')
            ->with('status', 'Stock converted.');
    }
}
