<?php

namespace App\Http\Controllers;

use App\Actions\RefundPosSale;
use App\Http\Requests\RefundPosSaleRequest;
use App\Http\Requests\SalesFilterRequest;
use App\Models\Branch;
use App\Models\PosSale;
use App\Support\EmployeeSession;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SalesReceiptController extends Controller
{
    public function index(SalesFilterRequest $request): Response
    {
        $filters = $request->filters();

        $receipts = PosSale::query()
            ->with(['branch:id,name', 'payments', 'items'])
            ->inPeriod($filters)
            ->when($filters->search !== '', function ($query) use ($filters): void {
                $search = '%'.$filters->search.'%';

                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', $search)
                        ->orWhere('sold_by_name', 'like', $search)
                        ->orWhere('sold_by_employee_number', 'like', $search);
                });
            })
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PosSale $sale): array => [
                'id' => $sale->id,
                'number' => $sale->number,
                'sold_at' => $sale->sold_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'branch' => $sale->branch?->name,
                'cashier' => $sale->sold_by_name,
                'payments' => $sale->payments->pluck('payment_method_name')->unique()->values()->implode(', ')
                    ?: $sale->payment_method_name,
                'item_count' => (int) $sale->items->sum('quantity'),
                'total' => Peso::format($sale->total),
                'is_refunded' => $sale->isRefunded(),
            ]);

        return Inertia::render('Sales/Receipts/Index', [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'receipts' => $receipts,
        ]);
    }

    public function show(PosSale $posSale): Response
    {
        $posSale->load(['branch:id,name', 'items', 'payments', 'taxes']);

        return Inertia::render('Sales/Receipts/Show', [
            'receipt' => [
                'id' => $posSale->id,
                'number' => $posSale->number,
                'sold_at' => $posSale->sold_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'branch' => $posSale->branch?->name,
                'cashier' => $posSale->sold_by_name,
                'employee_number' => $posSale->sold_by_employee_number,
                'delivery' => $posSale->delivery_method_name,
                'subtotal' => Peso::format($posSale->subtotal),
                'delivery_fee' => Peso::format($posSale->delivery_fee),
                'tax' => Peso::format($posSale->tax_total),
                'total' => Peso::format($posSale->total),
                'is_refunded' => $posSale->isRefunded(),
                'refunded_at' => $posSale->refunded_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'refunded_by' => $posSale->refunded_by_name,
                'refund_reason' => $posSale->refund_reason,
                'items' => $posSale->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product' => $item->product_name,
                    'variant' => $item->variant_name,
                    'quantity' => $item->quantity,
                    'unit_price' => Peso::format($item->unit_price),
                    'line_total' => Peso::format($item->line_total),
                    'notes' => $item->notes,
                    'modifiers' => $item->modifiers ?? [],
                ]),
                'payments' => $posSale->payments->map(fn ($payment): array => [
                    'name' => $payment->payment_method_name,
                    'amount' => Peso::format($payment->amount),
                    'tendered' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null,
                    'change' => $payment->is_cash ? Peso::format($payment->change_due) : null,
                    'reference' => $payment->reference_number,
                ]),
                'taxes' => $posSale->taxes->map(fn ($tax): array => [
                    'name' => $tax->name,
                    'amount' => Peso::format($tax->amount),
                ]),
            ],
        ]);
    }

    public function refund(RefundPosSaleRequest $request, PosSale $posSale, RefundPosSale $refundPosSale): RedirectResponse
    {
        $employee = EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''];
        $refundPosSale->handle($posSale, (string) $request->validated('reason'), $employee);

        return back()->with('status', 'Receipt '.$posSale->number.' refunded.');
    }
}
