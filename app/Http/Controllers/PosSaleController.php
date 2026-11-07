<?php

namespace App\Http\Controllers;

use App\Actions\RecordPosSale;
use App\Actions\SendShopOrderText;
use App\Actions\ShopOrderStock;
use App\Enums\PrinterRole;
use App\Enums\ShopOrderStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class PosSaleController extends Controller
{
    public function preview(StorePosSaleRequest $request, Branch $branch, RecordPosSale $recordPosSale, ShopOrderStock $shopOrderStock): JsonResponse
    {
        $openOrder = $this->onlineOrderForSale($this->shopOrder($branch, $request->validated('pos_open_order_id')));
        $quote = $recordPosSale->quote(
            $this->openShift($branch),
            $request->safe()->only([
                'items',
                'payments',
                'delivery_method_id',
                'delivery_fee',
                'tax_fee_ids',
            ]),
            $shopOrderStock->coveredQuantities($openOrder),
        );
        $printer = Printer::defaultFor(PrinterRole::Receipt, $branch);
        $width = $printer !== null && $printer->paper_width_mm <= 58 ? 32 : 48;

        return response()->json([
            'number' => $quote['number'],
            'open_drawer' => $quote['has_cash'],
            'checkout_success' => [
                'total' => Peso::format($quote['total']),
                'change' => $quote['has_cash'] ? Peso::format($quote['change_due']) : null,
            ],
            'receipt' => SaleReceipt::text([
                'sold_at' => now()->timezone((string) config('app.timezone'))->format('M j, Y g:i A'),
                'number' => $quote['number'],
                'items' => $quote['items'],
                'taxes' => $quote['taxes'],
                'total' => Peso::format($quote['total']),
                'tendered' => Peso::format($quote['amount_tendered']),
                'change' => Peso::format($quote['change_due']),
                'payments' => $quote['payments'],
            ], $width),
        ]);
    }

    public function store(StorePosSaleRequest $request, Branch $branch, RecordPosSale $recordPosSale, PosController $posController, SendShopOrderText $sendShopOrderText): JsonResponse|RedirectResponse|Response
    {
        $employee = EmployeeSession::current();
        $shopOrder = null;
        $sale = DB::transaction(function () use ($request, $branch, $recordPosSale, $employee, &$shopOrder): PosSale {
            $ticket = $this->shopOrder($branch, $request->validated('pos_open_order_id'));
            $onlineOrder = $this->onlineOrderForSale($ticket);
            $alreadyPreparing = $onlineOrder?->status === ShopOrderStatus::Preparing->value;
            $sale = $recordPosSale->handle(
                $this->openShift($branch),
                $request->safe()->only([
                    'items',
                    'payments',
                    'delivery_method_id',
                    'delivery_fee',
                    'tax_fee_ids',
                    'number',
                ]),
                $employee ?? ['employee_number' => '', 'name' => ''],
                $onlineOrder ?? $ticket,
            );

            if ($onlineOrder instanceof PosOpenOrder) {
                $onlineOrder->update([
                    'pos_sale_id' => $sale->id,
                    'status' => ShopOrderStatus::Preparing->value,
                ]);

                if (! $alreadyPreparing) {
                    $shopOrder = $onlineOrder;
                }
            }

            if ($ticket instanceof PosOpenOrder && (! is_string($ticket->status) || $ticket->status === '')) {
                $ticket->delete();
            }

            return $sale;
        });

        if ($shopOrder instanceof PosOpenOrder) {
            $sendShopOrderText->handle($shopOrder->fresh(['branch']) ?? $shopOrder);
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

    public function receipt(Branch $branch, PosSale $posSale): JsonResponse
    {
        abort_unless($posSale->branch_id === $branch->id, 404);

        return response()->json([
            'receipt' => $this->receiptText($posSale, $branch),
        ]);
    }

    private function receiptText(PosSale $sale, Branch $branch): string
    {
        $sale->loadMissing(['items', 'payments', 'taxes']);
        $printer = Printer::defaultFor(PrinterRole::Receipt, $branch);
        $width = $printer !== null && $printer->paper_width_mm <= 58 ? 32 : 48;
        $soldAt = $sale->sold_at?->timezone((string) config('app.timezone'))->format('M j, Y g:i A') ?? '';

        return SaleReceipt::text([
            'sold_at' => $soldAt,
            'number' => $sale->number,
            'items' => $sale->items
                ->map(fn (PosSaleItem $item): array => [
                    'name' => trim($item->product_name.' '.$item->variant_name.($item->is_free ? ' Free' : '')),
                    'quantity' => $item->quantity,
                    'subtotal' => Peso::format($item->line_total),
                ])
                ->all(),
            'taxes' => $sale->taxes
                ->map(fn ($tax): array => [
                    'name' => $tax->name,
                    'amount' => Peso::format($tax->amount),
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

    private function onlineOrderForSale(?PosOpenOrder $ticket): ?PosOpenOrder
    {
        if (! $ticket instanceof PosOpenOrder) {
            return null;
        }

        $checkout = is_array($ticket->checkout) ? $ticket->checkout : [];
        $linkedId = $checkout['online_order_id'] ?? null;

        if (is_numeric($linkedId)) {
            $linked = PosOpenOrder::query()
                ->where('branch_id', $ticket->branch_id)
                ->whereKey((int) $linkedId)
                ->first();

            if ($linked instanceof PosOpenOrder && is_string($linked->status) && $linked->status !== '') {
                return $linked;
            }
        }

        if (is_string($ticket->status) && $ticket->status !== '') {
            return $ticket;
        }

        return null;
    }

    private function shopOrder(Branch $branch, mixed $openOrderId): ?PosOpenOrder
    {
        if ($openOrderId === null) {
            return null;
        }

        $order = PosOpenOrder::query()
            ->where('branch_id', $branch->id)
            ->whereKey($openOrderId)
            ->first();

        return $order instanceof PosOpenOrder ? $order : null;
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
