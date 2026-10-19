<?php

namespace App\Http\Controllers;

use App\Enums\PrinterRole;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Printer;
use App\Support\Peso;
use App\Support\ZReportReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosZReportController extends Controller
{
    public function show(Request $request, Branch $branch, PosShift $posShift): JsonResponse|View
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

        $timezone = config('app.timezone');
        $printer = Printer::defaultFor(PrinterRole::Receipt, $branch);
        $itemCount = $sales->sum(fn (PosSale $sale): int => (int) $sale->items->sum('quantity'));
        $salesTotal = Peso::format($sales->sum('total'));
        $cashSales = Peso::format($posShift->cashSalesTotal());
        $cashIn = Peso::format($posShift->cashInTotal());
        $cashOut = Peso::format($posShift->cashOutTotal());
        $startingCash = Peso::format($posShift->starting_cash);
        $expectedCash = Peso::format($posShift->expected_cash);
        $countedCash = Peso::format($posShift->counted_cash);
        $variance = Peso::format($posShift->variance);
        $remitAmount = Peso::format($posShift->remit_amount);
        $receiptText = ZReportReceipt::text([
            'app' => (string) config('app.name'),
            'branch' => (string) $posShift->branch?->name,
            'opened' => 'Opened by '.$posShift->opened_by_name.' · '.$posShift->opened_at?->timezone($timezone)->format('M j, Y g:i A'),
            'closed' => 'Closed by '.$posShift->closed_by_name.' · '.$posShift->closed_at?->timezone($timezone)->format('M j, Y g:i A'),
            'payments' => $salesByPayment->all(),
            'item_count' => $itemCount,
            'sales_total' => $salesTotal,
            'starting_cash' => $startingCash,
            'cash_sales' => $cashSales,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'variance' => $variance,
            'remit_amount' => $remitAmount,
        ], $printer !== null && $printer->paper_width_mm <= 58 ? 32 : 48);

        if ($request->wantsJson()) {
            return response()->json([
                'printer' => $printer?->toSettingsPayload(),
                'receipt' => $receiptText,
            ]);
        }

        return view('pos.z-report', [
            'shift' => $posShift,
            'appName' => config('app.name'),
            'autoprint' => $request->boolean('print'),
            'printer' => $printer?->toSettingsPayload(),
            'receiptText' => $receiptText,
            'salesByPayment' => $salesByPayment,
            'itemCount' => $itemCount,
            'salesTotal' => $salesTotal,
            'cashSales' => $cashSales,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'startingCash' => $startingCash,
            'expectedCash' => $expectedCash,
            'countedCash' => $countedCash,
            'variance' => $variance,
            'remitAmount' => $remitAmount,
        ]);
    }
}
