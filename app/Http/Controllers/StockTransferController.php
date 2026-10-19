<?php

namespace App\Http\Controllers;

use App\Actions\TransferStock;
use App\Http\Requests\StoreStockTransferRequest;
use Illuminate\Http\RedirectResponse;

class StockTransferController extends Controller
{
    public function store(StoreStockTransferRequest $request, TransferStock $transferStock): RedirectResponse
    {
        $transferStock->handle($request->safe()->only([
            'from_branch_id',
            'to_branch_id',
            'catalog_item_variant_id',
            'quantity',
            'notes',
        ]));

        return redirect()
            ->route('inventory.index')
            ->with('status', 'Stock transferred.');
    }
}
