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
        return Inertia::render('Sales/Receipts/Index', [
            ...$this->listProps($request),
            'receipt' => null,
        ]);
    }

    public function show(SalesFilterRequest $request, PosSale $posSale): Response
    {
        $posSale->load(['branch:id,name', 'items', 'payments', 'taxes']);

        return Inertia::render('Sales/Receipts/Index', [
            ...$this->listProps($request),
            'receipt' => $this->receiptPayload($posSale),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listProps(SalesFilterRequest $request): array
    {
        $filters = $request->filters();

        $receipts = PosSale::query()
            ->with(['branch:id,name', 'payments', 'items', 'taxes'])
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
            ->through(fn (PosSale $sale): array => $this->receiptPayload($sale));

        return [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'receipts' => $receipts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(PosSale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'branch' => $sale->branch?->name,
            'cashier' => $sale->sold_by_name,
            'employee_number' => $sale->sold_by_employee_number,
            'delivery' => $sale->delivery_method_name,
            'subtotal' => Peso::format($sale->subtotal),
            'delivery_fee' => Peso::format($sale->delivery_fee),
            'tax' => Peso::format($sale->tax_total),
            'total' => Peso::format($sale->total),
            'is_refunded' => $sale->isRefunded(),
            'refunded_at' => $sale->refunded_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'refunded_by' => $sale->refunded_by_name,
            'refund_reason' => $sale->refund_reason,
            'items' => $sale->items->map(fn ($item): array => [
                'id' => $item->id,
                'product' => $item->product_name,
                'variant' => $item->is_free ? trim($item->variant_name.' · Free') : $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => Peso::format($item->unit_price),
                'line_total' => Peso::format($item->line_total),
                'notes' => $item->notes,
                'modifiers' => $item->modifiers ?? [],
            ]),
            'payments' => $sale->payments->map(fn ($payment): array => [
                'name' => $payment->payment_method_name,
                'amount' => Peso::format($payment->amount),
                'tendered' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null,
                'change' => $payment->is_cash ? Peso::format($payment->change_due) : null,
                'reference' => $payment->reference_number,
            ]),
            'taxes' => $sale->taxes->map(fn ($tax): array => [
                'name' => $tax->name,
                'amount' => Peso::format($tax->amount),
            ]),
        ];
    }

    public function refund(RefundPosSaleRequest $request, PosSale $posSale, RefundPosSale $refundPosSale): RedirectResponse
    {
        $employee = EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''];
        $refundPosSale->handle($posSale, (string) $request->validated('reason'), $employee);

        return back()->with('status', 'Receipt '.$posSale->number.' refunded.');
    }
}
