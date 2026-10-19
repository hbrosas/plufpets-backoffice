<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesFilterRequest;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\PosSalePayment;
use App\Support\Peso;
use App\Support\SalesFilters;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class SalesOverviewController extends Controller
{
    public function index(SalesFilterRequest $request): Response
    {
        $filters = $request->filters();
        $totals = PosSale::query()
            ->inPeriod($filters)
            ->sold()
            ->toBase()
            ->selectRaw('count(*) as receipts')
            ->selectRaw('coalesce(sum(total), 0) as total')
            ->selectRaw('coalesce(sum(tax_total), 0) as tax_total')
            ->first();

        $receipts = (int) ($totals->receipts ?? 0);
        $total = (float) ($totals->total ?? 0);
        $tax = (float) ($totals->tax_total ?? 0);

        return Inertia::render('Sales/Overview', [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => $this->branchOptions(),
            'summary' => [
                'receipts' => $receipts,
                'total' => Peso::format($total),
                'tax' => Peso::format($tax),
                'average' => Peso::format($receipts === 0 ? 0 : $total / $receipts),
            ],
            'by_branch' => $this->salesByBranch($filters),
            'by_payment' => $this->salesByPayment($filters),
            'daily' => $this->dailySales($filters),
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function branchOptions(): array
    {
        return Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * @return list<array{name: string, receipts: int, total: string}>
     */
    private function salesByBranch(SalesFilters $filters): array
    {
        $rows = PosSale::query()
            ->inPeriod($filters)
            ->sold()
            ->toBase()
            ->select('branch_id')
            ->selectRaw('count(*) as receipts')
            ->selectRaw('coalesce(sum(total), 0) as total')
            ->groupBy('branch_id')
            ->get();

        $names = Branch::query()
            ->whereIn('id', $rows->pluck('branch_id'))
            ->pluck('name', 'id');

        return $rows
            ->sortByDesc('total')
            ->map(fn (object $row): array => [
                'name' => $names[$row->branch_id] ?? 'Unknown branch',
                'receipts' => (int) $row->receipts,
                'total' => Peso::format($row->total),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, receipts: int, total: string}>
     */
    private function salesByPayment(SalesFilters $filters): array
    {
        return PosSalePayment::query()
            ->whereHas('sale', fn ($query) => $query->inPeriod($filters)->sold())
            ->toBase()
            ->select('payment_method_name')
            ->selectRaw('count(*) as receipts')
            ->selectRaw('coalesce(sum(amount), 0) as total')
            ->groupBy('payment_method_name')
            ->orderBy('payment_method_name')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->payment_method_name,
                'receipts' => (int) $row->receipts,
                'total' => Peso::format($row->total),
            ])
            ->all();
    }

    /**
     * @return list<array{day: string, day_label: string, receipts: int, total: string}>
     */
    private function dailySales(SalesFilters $filters): array
    {
        $rows = PosSale::query()
            ->inPeriod($filters)
            ->sold()
            ->toBase()
            ->selectRaw('date(sold_at) as day')
            ->selectRaw('count(*) as receipts')
            ->selectRaw('coalesce(sum(total), 0) as total')
            ->groupByRaw('date(sold_at)')
            ->orderByRaw('date(sold_at)')
            ->get()
            ->keyBy('day');

        $days = [];
        $end = CarbonImmutable::parse($filters->to->toDateString());

        for ($date = CarbonImmutable::parse($filters->from->toDateString()); $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $days[] = [
                'day' => $key,
                'day_label' => $date->timezone(config('app.timezone'))->format('M j'),
                'receipts' => (int) ($row->receipts ?? 0),
                'total' => Peso::format($row->total ?? 0),
                'total_amount' => (float) ($row->total ?? 0),
            ];
        }

        return $days;
    }
}
