<?php

namespace App\Http\Controllers;

use App\Actions\SavePosOpenOrder;
use App\Actions\ShopOrderStock;
use App\Http\Requests\StorePosOpenOrderRequest;
use App\Models\Branch;
use App\Models\PosOpenOrder;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PosOpenOrderController extends Controller
{
    public function store(StorePosOpenOrderRequest $request, Branch $branch, SavePosOpenOrder $savePosOpenOrder, PosController $posController): RedirectResponse|Response
    {
        $order = $savePosOpenOrder->handle(
            $branch,
            $request->safe()->only(['name', 'items', 'hotel_stay_id']),
            EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''],
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Open order '.$order->name.' saved.',
        ]);
    }

    public function update(
        StorePosOpenOrderRequest $request,
        Branch $branch,
        PosOpenOrder $posOpenOrder,
        SavePosOpenOrder $savePosOpenOrder,
        PosController $posController,
    ): RedirectResponse|Response {
        $order = $savePosOpenOrder->handle(
            $branch,
            $request->safe()->only(['name', 'items', 'hotel_stay_id']),
            EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''],
            $posOpenOrder,
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Open order '.$order->name.' updated.',
        ]);
    }

    public function destroy(Request $request, Branch $branch, string $pos_open_order, ShopOrderStock $shopOrderStock, PosController $posController): RedirectResponse|Response
    {
        $order = PosOpenOrder::query()->whereKey($pos_open_order)->first();

        if ($order instanceof PosOpenOrder && $order->branch_id !== $branch->id) {
            abort(404);
        }

        $number = null;

        if ($order instanceof PosOpenOrder) {
            $shopOrderStock->release($order);
            $number = $order->number;
            $order->delete();
        }

        return $posController->fromMutation($request, $branch, [
            'status' => $number === null ? 'Open order removed.' : 'Open order '.$number.' removed.',
        ]);
    }
}
