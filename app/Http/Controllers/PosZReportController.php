<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Support\Peso;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosZReportController extends Controller
{
    public function show(Request $request, Branch $branch, PosShift $posShift): View
    {
        abort_unless($posShift->branch_id === $branch->id, 404);
        abort_if($posShift->closed_at === null, 404);

        $posShift->load(['branch', 'sales.items', 'sales.payments', 'cashMovements', 'closingDenominations']);

        $sales = $posShift->sales->filter(fn (PosSale $sale): bool => ! $sale->isRefunded())->values();

        $salesByPayment = $sales
            ->flatMap(function (PosSale $sale) {
                if ($sale->payments->isNotEmpty()) {
                    return $sale->payments;
                }

                return collect([(object) [
                    'payment_method_name' => $sale->payment_method_name,
                    'amount' => $sale->total,
                ]]);
            })
            ->groupBy('payment_method_name')
            ->map(fn ($payments): array => [
                'name' => $payments->first()->payment_method_name,
                'count' => $payments->count(),
                'total' => Peso::format($payments->sum('amount')),
            ])
            ->values();

        return view('pos.z-report', [
            'shift' => $posShift,
            'appName' => config('app.name'),
            'autoprint' => $request->boolean('print'),
            'salesByPayment' => $salesByPayment,
            'itemCount' => $sales->sum(fn (PosSale $sale): int => (int) $sale->items->sum('quantity')),
            'salesTotal' => Peso::format($sales->sum('total')),
            'cashSales' => Peso::format($posShift->cashSalesTotal()),
            'cashIn' => Peso::format($posShift->cashInTotal()),
            'cashOut' => Peso::format($posShift->cashOutTotal()),
            'startingCash' => Peso::format($posShift->starting_cash),
            'expectedCash' => Peso::format($posShift->expected_cash),
            'countedCash' => Peso::format($posShift->counted_cash),
            'variance' => Peso::format($posShift->variance),
            'remitAmount' => Peso::format($posShift->remit_amount),
        ]);
    }
}
