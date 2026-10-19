<?php

namespace App\Http\Controllers;

use App\Actions\SavePosOpenOrder;
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
            $request->safe()->only(['name', 'items']),
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
            $request->safe()->only(['name', 'items']),
            EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''],
            $posOpenOrder,
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Open order '.$order->name.' updated.',
        ]);
    }

    public function destroy(Request $request, Branch $branch, PosOpenOrder $posOpenOrder, PosController $posController): RedirectResponse|Response
    {
        $number = $posOpenOrder->number;
        $posOpenOrder->delete();

        return $posController->fromMutation($request, $branch, [
            'status' => 'Open order '.$number.' removed.',
        ]);
    }
}
