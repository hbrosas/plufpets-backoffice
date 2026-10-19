<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesFilterRequest;
use App\Models\Branch;
use App\Models\PosSaleItem;
use App\Support\Peso;
use Inertia\Inertia;
use Inertia\Response;

class SalesItemController extends Controller
{
    public function index(SalesFilterRequest $request): Response
    {
        $filters = $request->filters();

        $items = PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->inPeriod($filters)->sold())
            ->toBase()
            ->select('product_name', 'variant_name')
            ->selectRaw('sum(quantity) as quantity')
            ->selectRaw('coalesce(sum(line_total), 0) as total')
            ->groupBy('product_name', 'variant_name')
            ->orderByDesc('total')
            ->orderBy('product_name')
            ->get()
            ->map(fn (object $row): array => [
                'product' => $row->product_name,
                'variant' => $row->variant_name,
                'quantity' => (int) $row->quantity,
                'total' => Peso::format($row->total),
            ]);

        return Inertia::render('Sales/Items/Index', [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'items' => $items,
        ]);
    }
}
