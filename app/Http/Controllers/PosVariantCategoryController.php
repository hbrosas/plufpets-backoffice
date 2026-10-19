<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePosVariantCategoryRequest;
use App\Models\CatalogItemVariant;
use Illuminate\Http\RedirectResponse;

class PosVariantCategoryController extends Controller
{
    public function update(UpdatePosVariantCategoryRequest $request, CatalogItemVariant $catalogItemVariant): RedirectResponse
    {
        $catalogItemVariant->update([
            'pos_category_id' => $request->validated('pos_category_id'),
        ]);

        return redirect()
            ->route('pos.categories.index')
            ->with('status', 'POS category assigned.');
    }
}
