<?php

namespace App\Http\Controllers;

use App\Actions\AssignStock;
use App\Http\Requests\StoreStockAssignmentRequest;
use App\Models\Branch;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockAssignmentController extends Controller
{
    public function index(): Response
    {
        $items = PurchaseOrderItem::query()
            ->with([
                'purchaseOrder:id,number',
                'variant:id,catalog_item_id,name,sku,price',
                'variant.catalogItem:id,name',
                'assignments.branch:id,name',
            ])
            ->withSum('assignments as assigned_quantity', 'quantity')
            ->where('received_quantity', '>', 0)
            ->latest('id')
            ->get()
            ->filter(fn (PurchaseOrderItem $item): bool => $item->received_quantity > (int) $item->assigned_quantity)
            ->values()
            ->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'purchase_order' => $item->purchaseOrder?->number,
                'product' => $item->variant?->catalogItem?->name,
                'variant' => $item->variant?->name,
                'sku' => $item->variant?->sku,
                'received_quantity' => $item->received_quantity,
                'assigned_quantity' => (int) $item->assigned_quantity,
                'remaining' => $item->received_quantity - (int) $item->assigned_quantity,
                'suggested_price' => $item->variant?->price,
            ]);

        return Inertia::render('Inventory/StockAssignments/Index', [
            'items' => $items,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreStockAssignmentRequest $request, AssignStock $assignStock): RedirectResponse
    {
        $assignStock->handle($request->safe()->only([
            'purchase_order_item_id',
            'branch_id',
            'quantity',
            'selling_price',
        ]));

        return redirect()
            ->route('inventory.stock-assignments.index')
            ->with('status', 'Stock assigned to the branch.');
    }
}
