<?php

namespace App\Http\Controllers;

use App\Enums\PosCashMovementType;
use App\Enums\PosDenominationCountType;
use App\Enums\PrinterRole;
use App\Http\Requests\SalesShiftFilterRequest;
use App\Models\Branch;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Support\CashDenominations;
use App\Support\Peso;
use Inertia\Inertia;
use Inertia\Response;

class SalesShiftController extends Controller
{
    public function index(SalesShiftFilterRequest $request): Response
    {
        $filters = $request->filters();

        $shifts = PosShift::query()
            ->with([
                'branch:id,name',
                'branch.printers' => fn ($query) => $query
                    ->where('role', PrinterRole::Receipt)
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('id'),
                'sales' => fn ($query) => $query->with([
                    'items:id,pos_sale_id,quantity',
                    'payments:id,pos_sale_id,payment_method_name,is_cash,amount',
                ]),
                'cashMovements',
                'denominations',
            ])
            ->inPeriod($filters)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PosShift $shift): array => $this->shiftPayload($shift));

        return Inertia::render('Sales/Shifts/Index', [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'shifts' => $shifts,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftPayload(PosShift $shift): array
    {
        $sold = $shift->sales->filter(fn (PosSale $sale): bool => ! $sale->isRefunded());
        $timezone = config('app.timezone');

        return [
            'id' => $shift->id,
            'branch' => $shift->branch?->name,
            'status' => $shift->isOpen() ? 'Open' : 'Closed',
            'opened_at' => $shift->opened_at?->timezone($timezone)->format('M j, Y g:i A'),
            'opened_by' => $shift->opened_by_name,
            'closed_at' => $shift->closed_at?->timezone($timezone)->format('M j, Y g:i A'),
            'closed_by' => $shift->closed_by_name,
            'receipts' => $sold->count(),
            'sales_total' => Peso::format($sold->sum('total')),
            'z_report_href' => $shift->isOpen() || $shift->branch_id === null
                ? null
                : route('pos.shifts.z-report', [$shift->branch_id, $shift], false),
            'z_report_print_href' => $shift->isOpen() || $shift->branch_id === null
                ? null
                : route('pos.shifts.z-report', [$shift->branch_id, $shift], false).'?print=1',
            'receipt_printer' => $shift->isOpen()
                ? null
                : $shift->branch?->printers->first()?->toSettingsPayload(),
            'z_report' => $this->zReport($shift),
        ];
    }

    /**
     * @return array{
     *     payments: list<array{name: string, count: int, total: string}>,
     *     item_count: int,
     *     sales_total: string,
     *     starting_cash: string,
     *     cash_sales: string,
     *     cash_in: string,
     *     cash_out: string,
     *     expected_cash: string,
     *     counted_cash: string,
     *     variance: string,
     *     remit_amount: string,
     *     opening_counts: list<array{denomination: string, quantity: int, total: string}>,
     *     closing_counts: list<array{denomination: string, quantity: int, total: string}>
     * }|null
     */
    private function zReport(PosShift $shift): ?array
    {
        if ($shift->isOpen()) {
            return null;
        }

        $sales = $shift->sales->filter(fn (PosSale $sale): bool => ! $sale->isRefunded())->values();
        $payments = $sales
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
            ->map(fn ($rows): array => [
                'name' => (string) $rows->first()->payment_method_name,
                'count' => $rows->count(),
                'total' => Peso::format($rows->sum('amount')),
            ])
            ->values()
            ->all();
        $cashSales = $sales->sum(fn (PosSale $sale): float => (float) $sale->payments
            ->where('is_cash', true)
            ->sum(fn ($payment): float => (float) $payment->amount));

        return [
            'payments' => $payments,
            'item_count' => (int) $sales->sum(fn (PosSale $sale): int => (int) $sale->items->sum('quantity')),
            'sales_total' => Peso::format($sales->sum('total')),
            'starting_cash' => Peso::format($shift->starting_cash),
            'cash_sales' => Peso::format($cashSales),
            'cash_in' => Peso::format($shift->cashMovements
                ->where('type', PosCashMovementType::CashIn)
                ->sum(fn (PosCashMovement $movement): float => (float) $movement->amount)),
            'cash_out' => Peso::format($shift->cashMovements
                ->where('type', PosCashMovementType::CashOut)
                ->sum(fn (PosCashMovement $movement): float => (float) $movement->amount)),
            'expected_cash' => Peso::format($shift->expected_cash),
            'counted_cash' => Peso::format($shift->counted_cash),
            'variance' => Peso::format($shift->variance),
            'remit_amount' => Peso::format($shift->remit_amount),
            'opening_counts' => CashDenominations::breakdown(
                $shift->denominations->where('count_type', PosDenominationCountType::Opening),
            ),
            'closing_counts' => CashDenominations::breakdown(
                $shift->denominations->where('count_type', PosDenominationCountType::Closing),
            ),
        ];
    }
}
