<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\Business;
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
     *     filters: array{branch: int|null, q: string, category: string},
     *     banner: array{image_url: string}|null,
     *     promotion: array{text: string, background_color: string, text_color: string}|null
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
            'products' => $branch instanceof Branch ? $this->products($branch) : [],
            'filters' => [
                'branch' => $branch instanceof Branch ? $branch->id : null,
                'q' => $search,
                'category' => $category,
            ],
            'banner' => $this->banner(),
            'promotion' => $this->promotion(),
        ];
    }

    /**
     * @return array{image_url: string}|null
     */
    private function banner(): ?array
    {
        $imageUrl = Business::current()?->shopBannerUrl();

        if ($imageUrl === null) {
            return null;
        }

        return [
            'image_url' => $imageUrl,
        ];
    }

    /**
     * @return array{text: string, background_color: string, text_color: string}|null
     */
    private function promotion(): ?array
    {
        $business = Business::current();
        $text = trim((string) $business?->shop_promo_text);

        if (! $business instanceof Business || ! $business->shop_promo_visible || $text === '') {
            return null;
        }

        return [
            'text' => $text,
            'background_color' => $this->hexColor($business->shop_promo_background, '#053b63'),
            'text_color' => $this->hexColor($business->shop_promo_color, '#ffffff'),
        ];
    }

    private function hexColor(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtolower($value)
            : $fallback;
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
    private function products(Branch $branch): array
    {
        return CatalogItemVariant::query()
            ->where('is_active', true)
            ->whereHas('catalogItem', function ($query): void {
                $query->where('is_active', true)
                    ->where(function ($query): void {
                        $query->whereNull('category_id')
                            ->orWhereHas('category', fn ($categories) => $categories->where('show_on_shop', true));
                    });
            })
            ->with([
                'catalogItem' => fn ($query) => $query
                    ->select('id', 'name', 'type', 'category_id', 'brand_id', 'badge', 'has_branch_pricing', 'allows_preorder', 'is_active')
                    ->with([
                        'category:id,name',
                        'brand:id,name,image_path',
                    ]),
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
        $itemName = $variant->catalogItem?->name ?? 'Product';
        $rawVariantName = trim((string) $variant->name);
        $variantName = $rawVariantName !== '' && strcasecmp($rawVariantName, 'Default') !== 0
            ? $rawVariantName
            : null;
        $name = $variantName === null ? $itemName : $itemName.' · '.$variantName;

        return [
            'id' => $variant->id,
            'name' => $name,
            'item_name' => $itemName,
            'variant_name' => $variantName,
            'price' => number_format($price, 2, '.', ''),
            'price_label' => Peso::format($price),
            'image_url' => $variant->imageUrl(),
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'category_id' => $variant->catalogItem?->category_id === null ? null : (string) $variant->catalogItem->category_id,
            'category_name' => $variant->catalogItem?->category?->name,
            'brand_id' => $variant->catalogItem?->brand_id === null ? null : (string) $variant->catalogItem->brand_id,
            'brand_name' => $variant->catalogItem?->brand?->name,
            'brand_logo_url' => $variant->catalogItem?->brand?->imageUrl(),
            'badge' => $variant->catalogItem?->badge,
            'is_product' => $isProduct,
            'stock' => $stock,
            'in_stock' => $stock === null || $stock > 0,
            'allows_preorder' => $isProduct && (bool) $variant->catalogItem?->allows_preorder,
            'stock_label' => match (true) {
                $stock === null => 'In Stock',
                $stock < 1 => 'Out of Stock',
                default => 'In Stock '.$stock,
            },
        ];
    }
}
