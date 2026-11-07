<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Http\Requests\SalesFilterRequest;
use App\Models\Branch;
use App\Models\PosSaleItem;
use App\Support\Peso;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SalesItemController extends Controller
{
    public function index(SalesFilterRequest $request): Response
    {
        $filters = $request->filters();

        $items = PosSaleItem::query()
            ->whereHas('sale', fn ($query) => $query->inPeriod($filters)->sold())
            ->leftJoin('catalog_item_variants', 'catalog_item_variants.id', '=', 'pos_sale_items.catalog_item_variant_id')
            ->leftJoin('catalog_items', 'catalog_items.id', '=', 'catalog_item_variants.catalog_item_id')
            ->toBase()
            ->select('pos_sale_items.product_name', 'pos_sale_items.variant_name', 'catalog_items.type')
            ->selectRaw('sum(pos_sale_items.quantity) as quantity')
            ->selectRaw('coalesce(sum(pos_sale_items.line_total), 0) as total')
            ->groupBy('pos_sale_items.product_name', 'pos_sale_items.variant_name', 'catalog_items.type')
            ->orderByDesc('total')
            ->orderBy('pos_sale_items.product_name')
            ->get()
            ->map(fn (object $row): array => [
                'type' => $row->type === CatalogItemType::Service->value ? 'service' : 'product',
                'product' => $row->product_name,
                'variant' => $row->variant_name,
                'quantity' => (int) $row->quantity,
                'total' => Peso::format($row->total),
            ]);

        return Inertia::render('Sales/Items/Index', [
            'filters' => $filters->toArray(),
            'period_label' => $filters->label(),
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'products' => $this->rowsFor($items, 'product'),
            'services' => $this->rowsFor($items, 'service'),
        ]);
    }

    /**
     * @param  Collection<int, array{type: string, product: string, variant: string, quantity: int, total: string}>  $items
     * @return Collection<int, array{product: string, variant: string, quantity: int, total: string}>
     */
    private function rowsFor(Collection $items, string $type): Collection
    {
        return $items
            ->where('type', $type)
            ->map(fn (array $row): array => [
                'product' => $row['product'],
                'variant' => $row['variant'],
                'quantity' => $row['quantity'],
                'total' => $row['total'],
            ])
            ->values();
    }
}
