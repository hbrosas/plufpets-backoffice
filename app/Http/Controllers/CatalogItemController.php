<?php

namespace App\Http\Controllers;

use App\Actions\SaveCatalogItem;
use App\Enums\CatalogItemType;
use App\Http\Requests\StoreCatalogItemRequest;
use App\Http\Requests\UpdateCatalogItemRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\Printer;
use App\Support\BarcodeSvg;
use App\Support\CatalogItemSupplies;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        $request->merge([
            'search' => $request->string('search')->trim()->toString() ?: null,
            'type' => $request->string('type')->trim()->toString() ?: null,
        ]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:128'],
            'type' => ['nullable', Rule::enum(CatalogItemType::class)],
        ]);

        $catalogItems = CatalogItem::query()
            ->with(['category:id,name', 'brand:id,name', 'variants'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('variants', function ($query) use ($search): void {
                            $query->where('sku', 'like', '%'.$search.'%')
                                ->orWhere('barcode', $search);
                        });
                });
            })
            ->when($filters['type'] ?? null, function ($query, string $type): void {
                $query->where('type', $type);
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CatalogItem $item): array => [
                'id' => $item->id,
                'type' => $item->type->value,
                'type_label' => $item->type->label(),
                'name' => $item->name,
                'image_url' => $item->imageUrl(),
                'category' => $item->category?->name,
                'brand' => $item->brand?->name,
                'variant_count' => $item->variants->count(),
                'is_active' => $item->is_active,
            ]);

        return Inertia::render('Shop/Catalog/Index', [
            'catalogItems' => $catalogItems,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
            ],
            'types' => $this->typeOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Shop/Catalog/Create', $this->formOptions());
    }

    public function store(StoreCatalogItemRequest $request, SaveCatalogItem $saveCatalogItem): RedirectResponse
    {
        $saveCatalogItem->handle($request->safe()->all());

        return redirect()
            ->route('shop.catalog.index')
            ->with('status', 'Catalog item created.');
    }

    public function show(CatalogItem $catalogItem, CatalogItemSupplies $supplies): Response
    {
        $catalogItem->load(['category:id,name', 'brand:id,name', 'variants']);

        return Inertia::render('Shop/Catalog/Show', [
            'catalogItem' => [
                'id' => $catalogItem->id,
                'type' => $catalogItem->type->value,
                'type_label' => $catalogItem->type->label(),
                'name' => $catalogItem->name,
                'description' => $catalogItem->description ?? '',
                'category' => $catalogItem->category?->name,
                'brand' => $catalogItem->brand?->name,
                'is_active' => $catalogItem->is_active,
                'variants' => $catalogItem->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'image_url' => $variant->imageUrl(),
                    'price' => Peso::format($variant->price),
                ])->all(),
            ],
            'supplies' => $supplies->list($catalogItem)->all(),
        ]);
    }

    public function labels(
        Request $request,
        CatalogItem $catalogItem,
        string $supply,
        CatalogItemSupplies $supplies,
    ): View|SymfonyResponse {
        $lot = $supplies->find($catalogItem, $supply);
        $copies = max(1, min(200, (int) $lot['quantity']));
        $variant = filled($lot['variant']) && strcasecmp((string) $lot['variant'], 'Default') !== 0
            ? $lot['variant']
            : null;

        $paper = Printer::labelPaper();
        $view = view('labels.sticker', [
            'title' => $catalogItem->name.' labels',
            'name' => $catalogItem->name,
            'variant' => $variant,
            'price' => $lot['retail_price'],
            'barcodeValue' => $lot['barcode'],
            'barcodeSvg' => BarcodeSvg::code39((string) $lot['barcode']),
            'dateLine' => $lot['packed_date'] ?: '—',
            'copies' => $copies,
            'widthMm' => $paper['width'],
            'heightMm' => $paper['height'],
            'downloadUrl' => route('shop.catalog.supplies.labels', [
                'catalog_item' => $catalogItem,
                'supply' => $lot['id'],
                'download' => 1,
            ]),
        ]);

        if ($request->boolean('download')) {
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $catalogItem->name) ?: 'labels';

            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'-labels.html"',
            ]);
        }

        return $view;
    }

    public function edit(CatalogItem $catalogItem): Response
    {
        $catalogItem->load([
            'category:id,name',
            'brand:id,name',
            'variants.branchPrices',
            'modifiers',
            'modifierOptionPrices.branchPrices',
        ]);

        return Inertia::render('Shop/Catalog/Edit', [
            ...$this->formOptions(),
            'catalogItem' => [
                'id' => $catalogItem->id,
                'type' => $catalogItem->type->value,
                'name' => $catalogItem->name,
                'category_name' => $catalogItem->category?->name ?? '',
                'brand_name' => $catalogItem->brand?->name ?? '',
                'description' => $catalogItem->description ?? '',
                'is_active' => $catalogItem->is_active,
                'has_branch_pricing' => $catalogItem->has_branch_pricing,
                'modifier_ids' => $catalogItem->modifiers->pluck('id')->all(),
                'modifier_prices' => $catalogItem->modifierOptionPrices->map(fn ($price): array => [
                    'modifier_id' => $price->modifier_id,
                    'option' => $price->option,
                    'price' => $price->price,
                    'branch_prices' => $price->branchPrices->map(fn ($branchPrice): array => [
                        'branch_id' => $branchPrice->branch_id,
                        'price' => $branchPrice->price,
                    ])->all(),
                ])->all(),
                'variants' => $catalogItem->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku ?? '',
                    'barcode' => $variant->barcode ?? '',
                    'image_url' => $variant->imageUrl(),
                    'price' => $variant->price,
                    'is_active' => $variant->is_active,
                    'branch_prices' => $variant->branchPrices->map(fn ($price): array => [
                        'branch_id' => $price->branch_id,
                        'price' => $price->price,
                    ])->all(),
                ])->all(),
            ],
        ]);
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem, SaveCatalogItem $saveCatalogItem): RedirectResponse
    {
        $saveCatalogItem->handle($request->safe()->all(), $catalogItem);

        return redirect()
            ->route('shop.catalog.index')
            ->with('status', 'Catalog item updated.');
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->load('variants');

        foreach ($catalogItem->variants as $variant) {
            if ($variant->image_path) {
                Storage::disk('public')->delete($variant->image_path);
            }
        }

        $catalogItem->delete();

        return redirect()
            ->route('shop.catalog.index')
            ->with('status', 'Catalog item deleted.');
    }

    /**
     * @return array{types: list<array{value: string, label: string}>, categories: list<string>, brands: list<string>, branches: list<array{id: int, name: string}>, modifiers: list<array{id: int, name: string, options: list<string>, is_active: bool}>}
     */
    private function formOptions(): array
    {
        return [
            'types' => $this->typeOptions(),
            'categories' => Category::query()->orderBy('name')->pluck('name')->all(),
            'brands' => Brand::query()->orderBy('name')->pluck('name')->all(),
            'branches' => Branch::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->all(),
            'modifiers' => Modifier::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'options', 'is_active'])
                ->map(fn (Modifier $modifier): array => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'options' => $modifier->options ?? [],
                    'is_active' => $modifier->is_active,
                ])
                ->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (CatalogItemType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            CatalogItemType::cases(),
        );
    }
}
