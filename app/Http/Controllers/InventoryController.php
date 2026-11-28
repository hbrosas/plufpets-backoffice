<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Brand;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\Printer;
use App\Models\PurchaseOrderItem;
use App\Support\BarcodeSvg;
use App\Support\InventoryCosts;
use App\Support\InventoryProductReport;
use App\Support\Peso;
use App\Support\XlsxWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryCosts $inventoryCosts): Response
    {
        $filters = $this->filters($request);
        $rows = $this->stockRows($filters, $inventoryCosts);
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
            'branches' => Branch::query()->operational()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->pluck('name')->all(),
            'brands' => Brand::query()->orderBy('name')->pluck('name')->all(),
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
                'category' => $filters['category'] ?? '',
                'brand' => $filters['brand'] ?? '',
            ],
        ]);
    }

    public function export(Request $request, InventoryCosts $inventoryCosts): SymfonyResponse
    {
        $filters = $this->filters($request);
        $request->merge([
            'sheet' => $request->string('sheet')->trim()->toString() ?: 'pricing',
        ]);
        $validated = $request->validate([
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'sheet' => ['required', Rule::in(['pricing', 'count'])],
        ], [
            'format.required' => 'Choose a PDF or Excel file.',
            'format.in' => 'Choose a PDF or Excel file.',
            'sheet.in' => 'Choose profit pricing or a stock count.',
        ]);
        $format = $validated['format'];
        $stockCount = $validated['sheet'] === 'count';
        $rows = $this->stockRows($filters, $inventoryCosts);
        $lines = $this->exportLines($rows, $stockCount);
        $unstockedLines = [];

        if ($stockCount) {
            [$lines, $unstockedLines] = $this->stockCountTables($lines, $rows, $filters);
        }

        $filename = 'inventory-'.($stockCount ? 'count-' : '').now()->toDateString().'.'.$format;
        $countedOn = now()->format('F j, Y');
        $subtitle = $this->exportSubtitle($filters, (int) $rows->sum('total_quantity'), (float) $rows->sum('value_amount'), $stockCount);
        $headers = $stockCount
            ? ['Brand', 'Product', 'Variant', 'SKU', 'Location', 'Expected', 'Count']
            : ['Product', 'Variant', 'SKU', 'Location', 'Qty', 'Purchase price', 'Retail price', 'Markup'];

        if ($format === 'xlsx') {
            $sheetRows = [$headers, ...$lines];

            if ($stockCount) {
                $blank = array_fill(0, count($headers) - 1, '');
                $sheetRows = [
                    ['Inventory Count', ...$blank],
                    [$countedOn, ...$blank],
                    $headers,
                    ...$lines,
                    array_fill(0, count($headers), ''),
                    ['No stock', ...$blank],
                    $headers,
                    ...($unstockedLines === [] ? [['No products without stock.', ...$blank]] : $unstockedLines),
                ];
            }

            return response(XlsxWorkbook::binary(
                $stockCount ? 'Inventory Count' : 'Inventory',
                $sheetRows,
                $stockCount ? 15 : null,
                $stockCount ? 5 : null,
                $stockCount ? [18, 34, 14, 16, 20, 10, 48] : null,
                $stockCount ? 3 : null,
            ), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return Pdf::loadView('inventory.export', [
            'title' => $stockCount ? 'Inventory Count' : 'Inventory',
            'countedOn' => $countedOn,
            'subtitle' => $subtitle,
            'lines' => $lines,
            'unstockedLines' => $unstockedLines,
            'stockCount' => $stockCount,
        ])->setPaper($stockCount ? 'legal' : 'a4', 'landscape')->download($filename);
    }

    /**
     * @return array{search: string|null, branch_id: int|null, category: string|null, brand: string|null}
     */
    private function filters(Request $request): array
    {
        $request->merge([
            'search' => $request->string('search')->trim()->toString() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
            'category' => $request->string('category')->trim()->toString() ?: null,
            'brand' => $request->string('brand')->trim()->toString() ?: null,
        ]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:128'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'search' => $filters['search'] ?? null,
            'branch_id' => $filters['branch_id'] ?? null,
            'category' => $filters['category'] ?? null,
            'brand' => $filters['brand'] ?? null,
        ];
    }

    /**
     * @param  array{search: string|null, branch_id: int|null, category: string|null, brand: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function stockRows(array $filters, InventoryCosts $inventoryCosts): Collection
    {
        $inventories = BranchInventory::query()
            ->operational()
            ->with([
                'branch:id,name',
                'variant:id,catalog_item_id,name,sku,price',
                'variant.catalogItem:id,name,brand_id',
                'variant.catalogItem.brand:id,name',
                'variant.branchPrices:id,branch_id,catalog_item_variant_id,price',
            ])
            ->when($filters['branch_id'] ?? null, fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('variant', function ($query) use ($search): void {
                    $query->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', $search)
                        ->orWhereHas('catalogItem', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                });
            });

        $this->constrainCatalog($inventories, $filters['category'] ?? null, $filters['brand'] ?? null);

        $inventories = $inventories->orderBy('id')->get();

        if ($filters['branch_id'] ?? null) {
            $unassigned = collect();
        } else {
            $unassigned = PurchaseOrderItem::query()
                ->with([
                    'variant:id,catalog_item_id,name,sku,price',
                    'variant.catalogItem:id,name,brand_id',
                    'variant.catalogItem.brand:id,name',
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
                });

            $this->constrainCatalog($unassigned, $filters['category'] ?? null, $filters['brand'] ?? null);

            $unassigned = $unassigned
                ->get()
                ->filter(fn (PurchaseOrderItem $item): bool => $item->received_quantity > (int) $item->assigned_quantity)
                ->values();
        }

        return $this->productRows($inventories, $unassigned, $inventoryCosts);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<list<int|string|null>>
     */
    private function exportLines(Collection $rows, bool $stockCount): array
    {
        $lines = [];

        foreach ($rows as $row) {
            foreach ($row['locations'] as $location) {
                $lines[] = $stockCount
                    ? [
                        $row['brand'] !== '' ? $row['brand'] : '—',
                        $row['product'] ?? '',
                        $row['variant'] ?? '',
                        $row['sku'] ?? '',
                        $location['name'],
                        $location['quantity'],
                        '',
                    ]
                    : [
                        $row['product'] ?? '',
                        $row['variant'] ?? '',
                        $row['sku'] ?? '',
                        $location['name'],
                        $location['quantity'],
                        $row['purchase_price'],
                        $row['retail_price'],
                        $row['markup'],
                    ];
            }
        }

        if ($stockCount) {
            usort($lines, fn (array $left, array $right): int => [$left[0], $left[1], $left[2], $left[4]] <=> [$right[0], $right[1], $right[2], $right[4]]);
        }

        return $lines;
    }

    /**
     * @param  list<list<int|string|null>>  $lines
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{search: string|null, branch_id: int|null, category: string|null, brand: string|null}  $filters
     * @return array{0: list<list<int|string|null>>, 1: list<list<int|string|null>>}
     */
    private function stockCountTables(array $lines, Collection $rows, array $filters): array
    {
        $onHand = [];
        $unstocked = [];

        foreach ($lines as $line) {
            if ((int) $line[5] > 0) {
                $onHand[] = $line;
            } else {
                $unstocked[] = $line;
            }
        }

        $unstocked = [
            ...$unstocked,
            ...$this->unstockedProductLines($filters, $rows->pluck('variant_id')->map(fn (mixed $id): int => (int) $id)->all()),
        ];

        usort($unstocked, fn (array $left, array $right): int => [$left[0], $left[1], $left[2], $left[4]] <=> [$right[0], $right[1], $right[2], $right[4]]);

        return [$onHand, $unstocked];
    }

    /**
     * Active products that have no inventory row in this export.
     *
     * @param  array{search: string|null, branch_id: int|null, category: string|null, brand: string|null}  $filters
     * @param  list<int>  $listedVariantIds
     * @return list<list<int|string|null>>
     */
    private function unstockedProductLines(array $filters, array $listedVariantIds): array
    {
        $location = '—';

        if ($filters['branch_id'] !== null) {
            $location = Branch::query()->whereKey($filters['branch_id'])->value('name') ?: 'Unknown';
        }

        $variants = CatalogItemVariant::query()
            ->with([
                'catalogItem:id,name,brand_id',
                'catalogItem.brand:id,name',
            ])
            ->where('is_active', true)
            ->whereHas('catalogItem', function (Builder $items) use ($filters): void {
                $items->where('type', CatalogItemType::Product)->where('is_active', true);

                if ($filters['category'] === '__none') {
                    $items->whereNull('category_id');
                } elseif (filled($filters['category'])) {
                    $items->whereHas('category', fn (Builder $category) => $category->where('name', $filters['category']));
                }

                if ($filters['brand'] === '__none') {
                    $items->whereNull('brand_id');
                } elseif (filled($filters['brand'])) {
                    $items->whereHas('brand', fn (Builder $brand) => $brand->where('name', $filters['brand']));
                }
            })
            ->when(filled($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', $search)
                        ->orWhereHas('catalogItem', fn (Builder $items) => $items->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($listedVariantIds !== [], fn (Builder $query) => $query->whereNotIn('id', $listedVariantIds))
            ->orderBy('id')
            ->get();

        return $variants
            ->map(fn (CatalogItemVariant $variant): array => [
                $variant->catalogItem?->brand?->name ?: '—',
                $variant->catalogItem?->name ?? '',
                $variant->name ?? '',
                $variant->sku ?? '',
                $location,
                0,
                '',
            ])
            ->all();
    }

    /**
     * @param  array{search: string|null, branch_id: int|null, category: string|null, brand: string|null}  $filters
     */
    private function exportSubtitle(array $filters, int $quantity, float $value, bool $stockCount): string
    {
        $parts = [];

        if (filled($filters['search'])) {
            $parts[] = 'Search: '.$filters['search'];
        }

        if (filled($filters['category'])) {
            $parts[] = 'Category: '.($filters['category'] === '__none' ? 'None' : $filters['category']);
        }

        if (filled($filters['brand'])) {
            $parts[] = 'Brand: '.($filters['brand'] === '__none' ? 'None' : $filters['brand']);
        }

        if ($filters['branch_id'] !== null) {
            $name = Branch::query()->whereKey($filters['branch_id'])->value('name');
            $parts[] = 'Location: '.($name ?: 'Unknown');
        }

        if (! $stockCount) {
            $parts[] = 'Total stock '.$quantity;
            $parts[] = 'Value '.Peso::format($value);
        }

        return implode(' · ', $parts);
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

        $paper = Printer::labelPaper();
        $view = view('labels.sticker', [
            'title' => $payload['product']['name'].' labels',
            'name' => $payload['product']['name'],
            'variant' => $variant,
            'price' => $payload['product']['retail_price'],
            'barcodeValue' => $barcodeValue,
            'barcodeSvg' => BarcodeSvg::code39($barcodeValue),
            'widthMm' => $paper['width'],
            'heightMm' => $paper['height'],
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
     * @param  Builder<BranchInventory>|Builder<PurchaseOrderItem>  $query
     */
    private function constrainCatalog(Builder $query, ?string $category, ?string $brand): void
    {
        $this->constrainCatalogColumn($query, 'category', $category);
        $this->constrainCatalogColumn($query, 'brand', $brand);
    }

    /**
     * @param  Builder<BranchInventory>|Builder<PurchaseOrderItem>  $query
     */
    private function constrainCatalogColumn(Builder $query, string $relation, ?string $name): void
    {
        if ($name === null) {
            return;
        }

        $query->whereHas('variant.catalogItem', function (Builder $items) use ($relation, $name): void {
            if ($name === '__none') {
                $items->whereNull($relation.'_id');

                return;
            }

            $items->whereHas($relation, fn (Builder $related) => $related->where('name', $name));
        });
    }

    /**
     * @param  Collection<int, BranchInventory>  $inventories
     * @param  Collection<int, PurchaseOrderItem>  $unassigned
     * @return Collection<int, array{
     *     id: int,
     *     variant_id: int,
     *     product: string|null,
     *     brand: string,
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
                    'brand' => $entry['variant']?->catalogItem?->brand?->name ?? '',
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
