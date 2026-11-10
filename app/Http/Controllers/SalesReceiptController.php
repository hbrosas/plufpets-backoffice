<?php

namespace App\Http\Controllers;

use App\Actions\RefundPosSale;
use App\Actions\WaivePosSaleCommission;
use App\Http\Requests\RefundPosSaleRequest;
use App\Http\Requests\SalesFilterRequest;
use App\Http\Requests\WaivePosSaleCommissionRequest;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
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
        $posSale->load(['branch:id,name', 'items.commissions', 'payments', 'taxes']);

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
            ->with(['branch:id,name', 'payments', 'items.commissions', 'taxes'])
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
            'can_waive_commissions' => EmployeeSession::allows('sales.waive-commission'),
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
            'commissions' => $this->commissionPayload($sale),
        ];
    }

    /**
     * @return list<array{id: int, employee_id: int|null, employee_number: string, employee_name: string, role: string, item: string, amount: string, waived: bool, waived_at: string|null, waived_by: string|null}>
     */
    private function commissionPayload(PosSale $sale): array
    {
        return $sale->items
            ->flatMap(fn (PosSaleItem $item) => $item->commissions->map(fn (PosSaleItemCommission $commission): array => [
                'id' => $commission->id,
                'employee_id' => $commission->employee_id,
                'employee_number' => $commission->employee_number,
                'employee_name' => $commission->employee_name,
                'role' => ucfirst($commission->role->value),
                'item' => $this->itemLabel($item),
                'amount' => Peso::format($commission->amount),
                'waived' => $commission->waived_at !== null,
                'waived_at' => $commission->waived_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
                'waived_by' => $commission->waived_by_name,
            ]))
            ->sortBy('id')
            ->values()
            ->all();
    }

    private function itemLabel(PosSaleItem $item): string
    {
        $variant = trim((string) $item->variant_name);

        if ($variant !== '' && strcasecmp($variant, 'Default') !== 0) {
            return $item->product_name.' · '.$variant;
        }

        return (string) $item->product_name;
    }

    public function refund(RefundPosSaleRequest $request, PosSale $posSale, RefundPosSale $refundPosSale): RedirectResponse
    {
        $employee = EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''];
        $refundPosSale->handle($posSale, (string) $request->validated('reason'), $employee);

        return back()->with('status', 'Receipt '.$posSale->number.' refunded.');
    }

    public function waiveCommission(WaivePosSaleCommissionRequest $request, PosSale $posSale, WaivePosSaleCommission $waivePosSaleCommission): RedirectResponse
    {
        $employee = EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''];
        $employeeId = $request->validated('employee_id');

        $waivePosSaleCommission->handle(
            $posSale,
            $employeeId === 'all' ? null : (int) $employeeId,
            $employee,
        );

        return redirect()
            ->route('sales.receipts.show', array_merge(
                ['pos_sale' => $posSale],
                $request->query(),
            ))
            ->with('status', 'Commission waived.');
    }
}
