<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use Illuminate\Http\Request;

class ShopCatalog
{
    /**
     * @return array{
     *     branches: list<array{id: int, name: string}>,
     *     branch: array{id: int, name: string}|null,
     *     categories: list<array{id: string, name: string}>,
     *     products: list<array<string, mixed>>,
     *     filters: array{branch: int|null, q: string, category: string}
     * }
     */
    public function page(Request $request): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $branch = $branches->firstWhere('id', $request->integer('branch')) ?? $branches->first();
        $search = $this->search($request->string('q')->toString());
        $categories = $branch instanceof Branch ? $this->categories() : [];
        $category = collect($categories)->contains(fn (array $row): bool => $row['id'] === $request->string('category')->toString())
            ? $request->string('category')->toString()
            : '';

        return [
            'branches' => $branches->map(fn (Branch $row): array => [
                'id' => $row->id,
                'name' => $row->name,
            ])->all(),
            'branch' => $branch instanceof Branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
            'categories' => $categories,
            'products' => $branch instanceof Branch ? $this->products($branch, $search, $category) : [],
            'filters' => [
                'branch' => $branch instanceof Branch ? $branch->id : null,
                'q' => $search,
                'category' => $category,
            ],
        ];
    }

    private function search(string $value): string
    {
        return mb_substr(trim(str_replace(['%', '_'], '', $value)), 0, 100);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function categories(): array
    {
        $categories = Category::query()
            ->where('show_on_shop', true)
            ->whereHas('catalogItems', function ($query): void {
                $query->where('is_active', true)
                    ->whereHas('variants', fn ($variants) => $variants->where('is_active', true));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => (string) $category->id,
                'name' => $category->name,
            ]);

        $hasUncategorized = CatalogItemVariant::query()
            ->where('is_active', true)
            ->whereHas('catalogItem', fn ($query) => $query->where('is_active', true)->whereNull('category_id'))
            ->exists();

        if ($hasUncategorized) {
            $categories->push(['id' => 'none', 'name' => 'Uncategorized']);
        }

        return $categories->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(Branch $branch, string $search, string $category): array
    {
        return CatalogItemVariant::query()
            ->where('is_active', true)
            ->whereHas('catalogItem', function ($query) use ($category): void {
                $query->where('is_active', true);

                if ($category === 'none') {
                    $query->whereNull('category_id');
                } elseif ($category !== '') {
                    $query->where('category_id', (int) $category)
                        ->whereHas('category', fn ($categories) => $categories->where('show_on_shop', true));
                } else {
                    $query->where(function ($query): void {
                        $query->whereNull('category_id')
                            ->orWhereHas('category', fn ($categories) => $categories->where('show_on_shop', true));
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhereHas('catalogItem', fn ($items) => $items->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->with([
                'catalogItem:id,name,type,category_id,has_branch_pricing,is_active',
                'inventories' => fn ($query) => $query->where('branch_id', $branch->id),
                'branchPrices' => fn ($query) => $query->where('branch_id', $branch->id),
            ])
            ->get()
            ->map(fn (CatalogItemVariant $variant): array => $this->product($variant, $branch->id))
            ->sortBy([
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function product(CatalogItemVariant $variant, int $branchId): array
    {
        $price = (float) $variant->price;

        if ($variant->catalogItem?->has_branch_pricing) {
            $branchPrice = $variant->branchPrices->firstWhere('branch_id', $branchId);

            if ($branchPrice !== null) {
                $price = (float) $branchPrice->price;
            }
        }

        $isProduct = $variant->catalogItem?->type === CatalogItemType::Product;
        $stock = $isProduct ? (int) ($variant->inventories->first()?->quantity ?? 0) : null;
        $name = $variant->catalogItem?->name ?? 'Product';
        $variantName = trim((string) $variant->name);

        if ($variantName !== '' && strcasecmp($variantName, 'Default') !== 0) {
            $name .= ' · '.$variantName;
        }

        return [
            'id' => $variant->id,
            'name' => $name,
            'price' => number_format($price, 2, '.', ''),
            'price_label' => Peso::format($price),
            'image_url' => $variant->imageUrl(),
            'is_product' => $isProduct,
            'stock' => $stock,
            'in_stock' => $stock === null || $stock > 0,
            'stock_label' => match (true) {
                $stock === null => 'In Stock',
                $stock < 1 => 'Out of Stock',
                default => 'In Stock '.$stock,
            },
        ];
    }
}
