<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrderItem;
use App\Support\BarcodeSvg;
use App\Support\InventoryCosts;
use App\Support\InventoryProductReport;
use App\Support\Peso;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryCosts $inventoryCosts): Response
    {
        $request->merge([
            'search' => $request->string('search')->trim()->toString() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
        ]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:128'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $inventories = BranchInventory::query()
            ->with([
                'branch:id,name',
                'variant:id,catalog_item_id,name,sku,price',
                'variant.catalogItem:id,name',
                'variant.branchPrices:id,branch_id,catalog_item_variant_id,price',
            ])
            ->when($filters['branch_id'] ?? null, fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('variant', function ($query) use ($search): void {
                    $query->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', $search)
                        ->orWhereHas('catalogItem', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('id')
            ->get();

        $unassigned = ($filters['branch_id'] ?? null)
            ? collect()
            : PurchaseOrderItem::query()
                ->with([
                    'variant:id,catalog_item_id,name,sku,price',
                    'variant.catalogItem:id,name',
                    'variant.branchPrices:id,branch_id,catalog_item_variant_id,price',
                ])
                ->withSum('assignments as assigned_quantity', 'quantity')
                ->where('received_quantity', '>', 0)
                ->when($filters['search'] ?? null, function ($query, string $search): void {
                    $query->whereHas('variant', function ($query) use ($search): void {
                        $query->where('sku', 'like', '%'.$search.'%')
                            ->orWhere('barcode', $search)
                            ->orWhereHas('catalogItem', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                    });
                })
                ->get()
                ->filter(fn (PurchaseOrderItem $item): bool => $item->received_quantity > (int) $item->assigned_quantity)
                ->values();

        $rows = $this->productRows($inventories, $unassigned, $inventoryCosts);
        $totalQuantity = (int) $rows->sum('total_quantity');
        $totalValue = (float) $rows->sum('value_amount');

        return Inertia::render('Inventory/Index', [
            'rows' => $rows->map(fn (array $row): array => [
                'id' => $row['id'],
                'variant_id' => $row['variant_id'],
                'product' => $row['product'],
                'variant' => $row['variant'],
                'sku' => $row['sku'],
                'locations' => $row['locations'],
                'total_quantity' => $row['total_quantity'],
                'purchase_price' => $row['purchase_price'],
                'purchase_price_amount' => $row['purchase_price_amount'],
                'retail_price' => $row['retail_price'],
                'markup' => $row['markup'],
                'can_move' => $row['can_move'],
            ])->values(),
            'summary' => [
                'quantity' => $totalQuantity,
                'value' => Peso::format($totalValue),
            ],
            'branches' => Branch::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'variants' => CatalogItemVariant::query()
                ->with('catalogItem:id,name,type')
                ->whereHas('catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product))
                ->orderBy('id')
                ->get()
                ->map(fn (CatalogItemVariant $variant): array => [
                    'id' => $variant->id,
                    'label' => $variant->catalogItem?->name.($variant->name !== 'Default' ? ' · '.$variant->name : ''),
                ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'branch_id' => $filters['branch_id'] ?? '',
            ],
        ]);
    }

    public function show(CatalogItemVariant $catalogItemVariant, InventoryProductReport $report): Response
    {
        return Inertia::render('Inventory/Show', $report->for($catalogItemVariant));
    }

    public function labels(Request $request, CatalogItemVariant $catalogItemVariant, InventoryProductReport $report): View|SymfonyResponse
    {
        $catalogItemVariant->load(['catalogItem:id,name']);
        $payload = $report->for($catalogItemVariant);
        $copies = max(1, min(48, $request->integer('copies', 1)));
        $barcodeValue = $catalogItemVariant->barcode ?: ($catalogItemVariant->sku ?: (string) $catalogItemVariant->id);

        $variant = filled($payload['product']['variant']) && strcasecmp((string) $payload['product']['variant'], 'Default') !== 0
            ? $payload['product']['variant']
            : null;

        $view = view('labels.sticker', [
            'title' => $payload['product']['name'].' labels',
            'name' => $payload['product']['name'],
            'variant' => $variant,
            'price' => $payload['product']['retail_price'],
            'barcodeValue' => $barcodeValue,
            'barcodeSvg' => BarcodeSvg::code39($barcodeValue),
            'dateLine' => count($payload['repack_dates']) > 0
                ? implode(', ', $payload['repack_dates'])
                : '—',
            'copies' => $copies,
            'downloadUrl' => route('inventory.products.labels', [
                'catalog_item_variant' => $catalogItemVariant,
                'copies' => $copies,
                'download' => 1,
            ]),
        ]);

        if ($request->boolean('download')) {
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $catalogItemVariant->sku) ?: 'label';

            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'-labels.html"',
            ]);
        }

        return $view;
    }

    /**
     * @param  Collection<int, BranchInventory>  $inventories
     * @param  Collection<int, PurchaseOrderItem>  $unassigned
     * @return Collection<int, array{
     *     id: int,
     *     variant_id: int,
     *     product: string|null,
     *     variant: string|null,
     *     sku: string|null,
     *     locations: list<array{id: string, branch_id: int|null, name: string, quantity: int, price: string}>,
     *     total_quantity: int,
     *     purchase_price: string,
     *     purchase_price_amount: float|null,
     *     retail_price: string,
     *     markup: string,
     *     can_move: bool,
     *     value_amount: float
     * }>
     */
    private function productRows(Collection $inventories, Collection $unassigned, InventoryCosts $inventoryCosts): Collection
    {
        /** @var Collection<int, array{variant: CatalogItemVariant|null, locations: Collection<int, array{id: string, branch_id: int|null, name: string, quantity: int, price: string}>}> $grouped */
        $grouped = collect();

        foreach ($inventories as $inventory) {
            $variantId = $inventory->catalog_item_variant_id;
            $entry = $grouped->get($variantId, [
                'variant' => $inventory->variant,
                'locations' => collect(),
            ]);

            $entry['locations']->push([
                'id' => 'branch-'.$inventory->branch_id,
                'branch_id' => $inventory->branch_id,
                'name' => $inventory->branch?->name ?? 'Unknown',
                'quantity' => $inventory->quantity,
                'price' => $this->locationPrice($inventory->variant, $inventory->branch_id),
            ]);

            $grouped->put($variantId, $entry);
        }

        foreach ($unassigned as $item) {
            $variantId = $item->catalog_item_variant_id;
            $quantity = $item->received_quantity - (int) $item->assigned_quantity;
            $entry = $grouped->get($variantId, [
                'variant' => $item->variant,
                'locations' => collect(),
            ]);
            $existing = $entry['locations']->firstWhere('id', 'unassigned');

            if ($existing === null) {
                $entry['locations']->push([
                    'id' => 'unassigned',
                    'branch_id' => null,
                    'name' => 'Unassigned',
                    'quantity' => $quantity,
                    'price' => $this->locationPrice($item->variant, null),
                ]);
            } else {
                $entry['locations'] = $entry['locations']->map(function (array $location) use ($quantity): array {
                    if ($location['id'] === 'unassigned') {
                        $location['quantity'] += $quantity;
                    }

                    return $location;
                });
            }

            $grouped->put($variantId, $entry);
        }

        $costs = $inventoryCosts->latestUnitCosts($grouped->keys()->all());

        return $grouped
            ->map(function (array $entry, int $variantId) use ($costs): array {
                $locations = $entry['locations']
                    ->sortBy(fn (array $location): array => [$location['name'] === 'Unassigned' ? 1 : 0, $location['name'], $location['id']])
                    ->values()
                    ->all();
                $totalQuantity = (int) collect($locations)->sum('quantity');
                $purchasePrice = $costs[$variantId] ?? null;
                $retailPrice = $entry['variant']?->price;

                return [
                    'id' => $variantId,
                    'variant_id' => $variantId,
                    'product' => $entry['variant']?->catalogItem?->name,
                    'variant' => $entry['variant']?->name,
                    'sku' => $entry['variant']?->sku,
                    'locations' => $locations,
                    'total_quantity' => $totalQuantity,
                    'purchase_price' => $purchasePrice === null ? '—' : Peso::format($purchasePrice),
                    'purchase_price_amount' => $purchasePrice,
                    'retail_price' => Peso::format($retailPrice),
                    'markup' => $this->formatMarkup($purchasePrice, $retailPrice),
                    'can_move' => collect($locations)->contains(fn (array $location): bool => $location['branch_id'] !== null && $location['quantity'] > 0),
                    'value_amount' => $purchasePrice === null ? 0.0 : $purchasePrice * $totalQuantity,
                ];
            })
            ->sortBy(fn (array $row): array => [$row['product'] ?? '', $row['variant'] ?? '', $row['id']])
            ->values();
    }

    private function locationPrice(?CatalogItemVariant $variant, ?int $branchId): string
    {
        $branchPrice = $branchId === null
            ? null
            : $variant?->branchPrices->firstWhere('branch_id', $branchId)?->price;

        return Peso::format($branchPrice ?? $variant?->price);
    }

    private function formatMarkup(?float $purchasePrice, mixed $retailPrice): string
    {
        if ($purchasePrice === null || $purchasePrice <= 0.0) {
            return '—';
        }

        $percent = (((float) $retailPrice - $purchasePrice) / $purchasePrice) * 100;

        return rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';
    }
}
