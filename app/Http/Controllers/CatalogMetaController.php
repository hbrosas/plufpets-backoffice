<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class CatalogMetaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Shop/CatalogMeta/Index', [
            'categories' => Category::query()
                ->with('posCategory')
                ->withCount('catalogItems')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'pos_category_id' => $category->pos_category_id,
                    'pos_category_name' => $category->posCategory?->name ?? 'Unassigned',
                    'item_count' => $category->catalog_items_count,
                ]),
            'brands' => Brand::query()
                ->withCount('catalogItems')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Brand $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'image_url' => $brand->imageUrl(),
                    'item_count' => $brand->catalog_items_count,
                ]),
        ]);
    }
}
