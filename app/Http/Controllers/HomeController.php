<?php

namespace App\Http\Controllers;

use App\Actions\PlaceShopOrder;
use App\Http\Requests\StoreShopOrderRequest;
use App\Models\Branch;
use App\Support\Peso;
use App\Support\ShopCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, ShopCatalog $catalog): Response
    {
        return Inertia::render('Site/Home', $catalog->page($request));
    }

    public function store(StoreShopOrderRequest $request, PlaceShopOrder $placeShopOrder): RedirectResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $order = $placeShopOrder->handle(
            $branch,
            $request->string('name')->toString(),
            $request->string('phone')->toString(),
            $request->validated('items'),
        );

        return redirect()
            ->route('home', ['branch' => $branch->id])
            ->with('status', 'Order '.$order->number.' is saved. Pay when you pick it up at '.$branch->name.'.')
            ->with('checkout_success', [
                'number' => $order->number,
                'total' => Peso::format($order->subtotal),
                'branch' => $branch->name,
            ]);
    }
}
