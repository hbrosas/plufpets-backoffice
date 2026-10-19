<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePosCategoryRequest;
use App\Http\Requests\UpdatePosCategoryRequest;
use App\Models\PosCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PosCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pos/Categories/Index', [
            'categories' => PosCategory::query()
                ->with('shopCategories')
                ->withCount('shopCategories')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PosCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'is_active' => $category->is_active,
                    'shop_category_count' => $category->shop_categories_count,
                    'shop_categories' => $category->shopCategories->pluck('name')->values(),
                ]),
        ]);
    }

    public function store(StorePosCategoryRequest $request): RedirectResponse
    {
        $maxOrder = (int) PosCategory::query()->max('sort_order');

        PosCategory::query()->create([
            ...$request->safe()->only(['name', 'is_active']),
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()
            ->route('pos.categories.index')
            ->with('status', 'POS category created.');
    }

    public function update(UpdatePosCategoryRequest $request, PosCategory $posCategory): RedirectResponse
    {
        $posCategory->update($request->safe()->only(['name', 'is_active']));

        return redirect()
            ->route('pos.categories.index')
            ->with('status', 'POS category updated.');
    }

    public function destroy(PosCategory $posCategory): RedirectResponse
    {
        $posCategory->delete();

        return redirect()
            ->route('pos.categories.index')
            ->with('status', 'POS category deleted.');
    }
}
