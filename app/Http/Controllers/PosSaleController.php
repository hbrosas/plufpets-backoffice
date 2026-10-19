<?php

namespace App\Http\Controllers;

use App\Actions\RecordPosSale;
use App\Enums\PrinterRole;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\PosShift;
use App\Models\Printer;
use App\Support\EmployeeSession;
use App\Support\Peso;
use App\Support\SaleReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class PosSaleController extends Controller
{
    public function store(StorePosSaleRequest $request, Branch $branch, RecordPosSale $recordPosSale, PosController $posController): JsonResponse|RedirectResponse|Response
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

        $notice = [
            'status' => 'Sale '.$sale->number.' recorded.',
            'open_drawer' => $sale->is_cash,
            'checkout_success' => [
                'total' => Peso::format($sale->total),
                'change' => $sale->is_cash ? Peso::format($sale->change_due) : null,
            ],
        ];

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                ...$notice,
                'receipt' => $this->receiptText($sale, $branch),
            ]);
        }

        return $posController->fromMutation($request, $branch, $notice);
    }

    private function receiptText(PosSale $sale, Branch $branch): string
    {
        $printer = Printer::defaultFor(PrinterRole::Receipt, $branch);
        $width = $printer !== null && $printer->paper_width_mm <= 58 ? 32 : 48;
        $soldAt = $sale->sold_at?->timezone((string) config('app.timezone'))->format('M j, Y g:i A') ?? '';

        return SaleReceipt::text([
            'sold_at' => $soldAt,
            'number' => $sale->number,
            'items' => $sale->items
                ->map(fn (PosSaleItem $item): array => [
                    'name' => trim($item->product_name.' '.$item->variant_name),
                    'quantity' => $item->quantity,
                    'subtotal' => Peso::format($item->line_total),
                ])
                ->all(),
            'total' => Peso::format($sale->total),
            'tendered' => Peso::format($sale->amount_tendered),
            'change' => Peso::format($sale->change_due),
            'payments' => $sale->payments
                ->map(fn (PosSalePayment $payment): array => [
                    'name' => $payment->payment_method_name,
                    'reference' => $payment->reference_number,
                ])
                ->all(),
        ], $width);
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
