<?php

namespace App\Http\Controllers;

use App\Actions\AdjustStock;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Support\EmployeeSession;
use App\Support\StockAdjustmentLines;
use App\Support\TestingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function create(Request $request, StockAdjustmentLines $lines): Response
    {
        $filters = $this->filters($request);
        $ready = $filters['branch_id'] !== null && match ($filters['scope']) {
            'brand' => $filters['brand_id'] !== null,
            'category' => $filters['category_id'] !== null,
            default => true,
        };

        return Inertia::render('Inventory/Adjustments', [
            'branches' => Branch::query()->operational()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'filters' => [
                'scope' => $filters['scope'],
                'brand_id' => $filters['brand_id'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
                'branch_id' => $filters['branch_id'] ?? '',
            ],
            'lines' => $ready
                ? $lines->for($filters['scope'], $filters['brand_id'], $filters['category_id'], (int) $filters['branch_id'])
                : [],
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, AdjustStock $adjustStock): RedirectResponse
    {
        $validated = $request->validated();
        $employee = EmployeeSession::current() ?? [
            'employee_number' => '',
            'name' => '',
        ];
        $adjustment = $adjustStock->handle([
            'branch_id' => (int) $validated['branch_id'],
            'scope' => (string) $validated['scope'],
            'brand_id' => $validated['brand_id'] === null ? null : (int) $validated['brand_id'],
            'category_id' => $validated['category_id'] === null ? null : (int) $validated['category_id'],
            'record_as_sale' => (bool) $validated['record_as_sale'],
            'lines' => array_map(fn (array $line): array => [
                'catalog_item_variant_id' => (int) $line['catalog_item_variant_id'],
                'quantity' => (int) $line['quantity'],
            ], $validated['lines']),
        ], $employee);

        return redirect()
            ->route('inventory.index')
            ->with('status', $adjustment->pos_sale_id === null
                ? 'Stock adjusted.'
                : 'Stock adjusted and recorded as a sale.');
    }

    /**
     * @return array{scope: string, brand_id: int|null, category_id: int|null, branch_id: int|null}
     */
    private function filters(Request $request): array
    {
        $request->merge([
            'scope' => $request->string('scope')->trim()->toString() ?: 'all',
            'brand_id' => $request->integer('brand_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
        ]);

        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'brand', 'category'])],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'branch_id' => ['nullable', 'integer', TestingMode::existsBranch()],
        ]);

        return [
            'scope' => $filters['scope'] ?? 'all',
            'brand_id' => $filters['brand_id'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
            'branch_id' => $filters['branch_id'] ?? null,
        ];
    }
}
