<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmPurchaseOrder;
use App\Actions\SaveCatalogItem;
use App\Actions\SavePurchaseOrder;
use App\Enums\CatalogItemType;
use App\Http\Requests\StorePurchaseOrderProductRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaseOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inventory/PurchaseOrders/Index', [
            'purchaseOrders' => PurchaseOrder::query()
                ->with(['supplier:id,name', 'items'])
                ->latest('id')
                ->paginate(15)
                ->through(fn (PurchaseOrder $order): array => $this->summary($order)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/PurchaseOrders/Create', $this->formOptions());
    }

    public function store(StorePurchaseOrderRequest $request, SavePurchaseOrder $savePurchaseOrder): RedirectResponse
    {
        $order = $savePurchaseOrder->handle($request->safe()->only(['supplier_id', 'branch_id', 'notes', 'items']));

        return redirect()
            ->route('inventory.purchase-orders.show', $order)
            ->with('status', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'supplier',
            'branch',
            'items.variant.catalogItem',
            'receipts.items.purchaseOrderItem.variant.catalogItem',
        ]);

        return Inertia::render('Inventory/PurchaseOrders/Show', [
            'purchaseOrder' => $this->detail($purchaseOrder),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($purchaseOrder->isDraft(), 404);

        $purchaseOrder->load(['items.variant.catalogItem']);

        return Inertia::render('Inventory/PurchaseOrders/Edit', [
            ...$this->formOptions(),
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'branch_id' => $purchaseOrder->branch_id,
                'notes' => $purchaseOrder->notes ?? '',
                'items' => $purchaseOrder->items->map(fn ($item): array => [
                    'catalog_item_variant_id' => $item->catalog_item_variant_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                ])->all(),
            ],
        ]);
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        SavePurchaseOrder $savePurchaseOrder,
    ): RedirectResponse {
        $savePurchaseOrder->handle($request->safe()->only(['supplier_id', 'branch_id', 'notes', 'items']), $purchaseOrder);

        return redirect()
            ->route('inventory.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 404);

        $purchaseOrder->delete();

        return redirect()
            ->route('inventory.purchase-orders.index')
            ->with('status', 'Purchase order deleted.');
    }

    public function confirm(PurchaseOrder $purchaseOrder, ConfirmPurchaseOrder $confirmPurchaseOrder): RedirectResponse
    {
        $confirmPurchaseOrder->handle($purchaseOrder);

        return redirect()
            ->route('inventory.purchase-orders.show', $purchaseOrder)
            ->with('status', 'Purchase order confirmed.');
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder): View|SymfonyResponse
    {
        $purchaseOrder->load(['supplier', 'branch', 'items.variant.catalogItem']);

        $view = view('inventory.purchase-orders.print', [
            'purchaseOrder' => $purchaseOrder,
            'appName' => config('app.name'),
        ]);

        if ($request->boolean('download')) {
            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$purchaseOrder->number.'.html"',
            ]);
        }

        return $view;
    }

    public function storeProduct(StorePurchaseOrderProductRequest $request, SaveCatalogItem $saveCatalogItem): RedirectResponse
    {
        $item = $saveCatalogItem->handle([
            'type' => CatalogItemType::Product,
            'name' => $request->validated('name'),
            'category_name' => $request->validated('category_name'),
            'brand_name' => $request->validated('brand_name'),
            'description' => null,
            'is_active' => true,
            'has_branch_pricing' => false,
            'variants' => [[
                'name' => 'Default',
                'sku' => $request->validated('sku'),
                'barcode' => $request->validated('barcode'),
                'price' => $request->validated('selling_price'),
                'is_active' => true,
            ]],
            'modifier_ids' => [],
        ]);

        $variant = $item->variants()->first();

        return back()->with([
            'status' => 'Product added to the catalog.',
            'new_variant' => [
                'id' => $variant?->id,
                'label' => $item->name,
                'unit_cost' => $request->validated('unit_cost'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'contact_name', 'email', 'phone', 'address']),
            'variants' => CatalogItemVariant::query()
                ->with('catalogItem:id,name,type')
                ->whereHas('catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product))
                ->orderBy('id')
                ->get()
                ->map(fn (CatalogItemVariant $variant): array => [
                    'id' => $variant->id,
                    'label' => $variant->catalogItem?->name.($variant->name !== 'Default' ? ' · '.$variant->name : ''),
                    'sku' => $variant->sku,
                ]),
            'categories' => Category::query()->orderBy('name')->orderBy('id')->pluck('name'),
            'brands' => Brand::query()->orderBy('name')->orderBy('id')->pluck('name'),
            'branches' => Branch::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'registered_business_name', 'address', 'tin']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'supplier' => $order->supplier?->name,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'item_count' => $order->items->count(),
            'ordered_quantity' => $order->orderedQuantity(),
            'received_quantity' => $order->receivedQuantity(),
            'total' => Peso::format($order->totalAmount()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PurchaseOrder $order): array
    {
        return [
            ...$this->summary($order),
            'notes' => $order->notes,
            'ordered_at' => $order->ordered_at?->toDayDateTimeString(),
            'confirmed_at' => $order->confirmed_at?->toDayDateTimeString(),
            'is_draft' => $order->isDraft(),
            'items' => $order->items->map(fn ($item): array => [
                'id' => $item->id,
                'product' => $item->variant?->catalogItem?->name,
                'variant' => $item->variant?->name,
                'sku' => $item->variant?->sku,
                'quantity' => $item->quantity,
                'unit_cost' => Peso::format($item->unit_cost),
                'received_quantity' => $item->received_quantity,
                'remaining' => $item->remainingToReceive(),
                'line_total' => Peso::format($item->lineTotal()),
            ])->all(),
            'business' => [
                'branch' => $order->branch?->name,
                'registered_business_name' => $order->branch?->registered_business_name,
                'address' => $order->branch?->address,
                'tin' => $order->branch?->tin,
            ],
            'receipts' => $order->receiptHistory(),
            'supplier_detail' => [
                'name' => $order->supplier?->name,
                'contact_name' => $order->supplier?->contact_name,
                'email' => $order->supplier?->email,
                'phone' => $order->supplier?->phone,
                'address' => $order->supplier?->address,
            ],
        ];
    }
}
