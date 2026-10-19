<?php

namespace App\Http\Controllers;

use App\Enums\PosCashMovementType;
use App\Http\Requests\SalesFilterRequest;
use App\Models\Branch;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Support\Peso;
use Inertia\Inertia;
use Inertia\Response;

class SalesShiftController extends Controller
{
    public function index(SalesFilterRequest $request): Response
    {
        $filters = $request->filters();

        $shifts = PosShift::query()
            ->with([
                'branch:id,name',
                'sales' => fn ($query) => $query->orderBy('sold_at')->orderBy('id')->with([
                    'items:id,pos_sale_id,product_name,variant_name,quantity,line_total',
                    'payments:id,pos_sale_id,payment_method_name,amount',
                ]),
                'cashMovements',
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
            'transactions' => $this->transactions($shift),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transactions(PosShift $shift): array
    {
        $timezone = config('app.timezone');
        $rows = collect();

        if ((float) $shift->starting_cash > 0) {
            $rows->push([
                'key' => 'starting-'.$shift->id,
                'sort' => $shift->opened_at?->timestamp ?? 0,
                'type' => 'Starting cash',
                'title' => 'Starting cash',
                'time' => $shift->opened_at?->timezone($timezone)->format('g:i A'),
                'actor' => $shift->opened_by_name,
                'detail' => '',
                'amount' => Peso::format($shift->starting_cash),
            ]);
        }

        foreach ($shift->sales as $sale) {
            $rows->push([
                'key' => 'sale-'.$sale->id,
                'sort' => $sale->sold_at?->timestamp ?? 0,
                'type' => $sale->isRefunded() ? 'Refunded sale' : 'Sale',
                'title' => $sale->number,
                'time' => $sale->sold_at?->timezone($timezone)->format('g:i A'),
                'actor' => $sale->sold_by_name,
                'detail' => $sale->items->map(function ($item): string {
                    $label = $item->variant_name === 'Default'
                        ? $item->product_name
                        : $item->product_name.' · '.$item->variant_name;

                    return $item->quantity.' × '.$label;
                })->implode(', '),
                'amount' => ($sale->isRefunded() ? '-' : '').Peso::format($sale->total),
            ]);
        }

        foreach ($shift->cashMovements as $movement) {
            /** @var PosCashMovement $movement */
            $isCashOut = $movement->type === PosCashMovementType::CashOut;
            $rows->push([
                'key' => 'cash-'.$movement->id,
                'sort' => $movement->recorded_at?->timestamp ?? 0,
                'type' => $movement->type->label(),
                'title' => $movement->type->label(),
                'time' => $movement->recorded_at?->timezone($timezone)->format('g:i A'),
                'actor' => $movement->employee_name,
                'detail' => $movement->reason,
                'amount' => ($isCashOut ? '-' : '').Peso::format($movement->amount),
            ]);
        }

        return $rows
            ->sortByDesc('sort')
            ->values()
            ->map(function (array $row): array {
                unset($row['sort']);

                return $row;
            })
            ->all();
    }
}
