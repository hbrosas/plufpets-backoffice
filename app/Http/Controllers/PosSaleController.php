<?php

namespace App\Http\Controllers;

use App\Actions\RecordPosSale;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\PosOpenOrder;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class PosSaleController extends Controller
{
    public function store(StorePosSaleRequest $request, Branch $branch, RecordPosSale $recordPosSale, PosController $posController): RedirectResponse|Response
    {
        $employee = EmployeeSession::current();
        $sale = $recordPosSale->handle(
            $this->openShift($branch),
            $request->safe()->only([
                'items',
                'payments',
                'delivery_method_id',
                'delivery_fee',
            ]),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        $openOrderId = $request->validated('pos_open_order_id');

        if ($openOrderId !== null) {
            PosOpenOrder::query()
                ->where('branch_id', $branch->id)
                ->whereKey($openOrderId)
                ->delete();
        }

        return $posController->fromMutation($request, $branch, [
            'status' => 'Sale '.$sale->number.' recorded.',
            'open_drawer' => $sale->is_cash,
            'checkout_success' => [
                'total' => Peso::format($sale->total),
                'change' => $sale->is_cash ? Peso::format($sale->change_due) : null,
            ],
        ]);
    }

    private function openShift(Branch $branch): PosShift
    {
        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        if ($shift === null) {
            throw ValidationException::withMessages([
                'status' => 'Open a shift before recording a sale.',
            ]);
        }

        return $shift;
    }
}
