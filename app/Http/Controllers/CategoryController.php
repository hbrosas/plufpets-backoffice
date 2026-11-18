<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->safe()->only(['name', 'pos_category_id', 'show_on_shop']));

        return back()->with('status', 'Category created.')->with('created_category', $category->name);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->safe()->only(['name', 'pos_category_id', 'show_on_shop']));

        return back()->with('status', 'Category updated.');
    }

    public function bulk(BulkUpdateCategoriesRequest $request): RedirectResponse
    {
        $action = $request->validated('action');

        $changes = match ($action) {
            'pos_category' => [
                'pos_category_id' => $request->integer('pos_category_id'),
            ],
            'visibility' => [
                'show_on_shop' => $request->boolean('show_on_shop'),
            ],
        };

        Category::query()->whereIn('id', $request->validated('ids'))->update($changes);

        $message = match ($action) {
            'pos_category' => 'POS category updated.',
            'visibility' => 'Public shop visibility updated.',
        };

        return back()->with('status', $message);
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }
}
