<?php

namespace App\Http\Controllers;

use App\Actions\RefundPosSale;
use App\Actions\WaivePosSaleCommission;
use App\Http\Requests\RefundPosSaleRequest;
use App\Http\Requests\SalesFilterRequest;
use App\Http\Requests\WaivePosSaleCommissionRequest;
use App\Models\Branch;
use App\Models\PosSale;
use App\Support\EmployeeSession;
use App\Support\PosSaleDetails;
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
        return PosSaleDetails::payload($sale);
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
