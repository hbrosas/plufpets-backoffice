<?php

namespace App\Http\Controllers;

use App\Actions\AssignStock;
use App\Http\Requests\StoreStockAssignmentRequest;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockAssignmentController extends Controller
{
    public function index(): Response
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $items = PurchaseOrderItem::query()
            ->with([
                'purchaseOrder:id,number',
                'variant:id,catalog_item_id,name,sku',
                'variant.catalogItem:id,name',
            ])
            ->withSum('assignments as assigned_quantity', 'quantity')
            ->where('received_quantity', '>', 0)
            ->latest('id')
            ->get()
            ->filter(fn (PurchaseOrderItem $item): bool => $item->received_quantity > (int) $item->assigned_quantity)
            ->values();

        $stock = $items->isEmpty()
            ? collect()
            : BranchInventory::query()
                ->whereIn('branch_id', $branches->modelKeys())
                ->whereIn('catalog_item_variant_id', $items->pluck('catalog_item_variant_id')->unique()->all())
                ->get()
                ->keyBy(fn (BranchInventory $row): string => $row->catalog_item_variant_id.'-'.$row->branch_id);

        return Inertia::render('Inventory/StockAssignments/Index', [
            'items' => $items->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'purchase_order' => $item->purchaseOrder?->number,
                'product' => $item->variant?->catalogItem?->name,
                'variant' => $item->variant?->name,
                'sku' => $item->variant?->sku,
                'received_quantity' => $item->received_quantity,
                'assigned_quantity' => (int) $item->assigned_quantity,
                'remaining' => $item->received_quantity - (int) $item->assigned_quantity,
                'branch_quantities' => $branches->map(fn (Branch $branch): int => (int) ($stock->get($item->catalog_item_variant_id.'-'.$branch->id)?->quantity ?? 0))->values()->all(),
            ])->all(),
            'branches' => $branches,
        ]);
    }

    public function store(StoreStockAssignmentRequest $request, AssignStock $assignStock): RedirectResponse
    {
        $assignStock->handle($request->integer('branch_id'), $request->validated('items'));

        return redirect()
            ->route('inventory.stock-assignments.index')
            ->with('status', 'Stock assigned to the branch.');
    }
}
