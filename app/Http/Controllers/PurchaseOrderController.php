<?php

namespace App\Http\Controllers;

use App\Actions\ClosePurchaseOrder;
use App\Actions\ConfirmPurchaseOrder;
use App\Actions\SaveCatalogItem;
use App\Actions\SavePurchaseOrder;
use App\Enums\CatalogItemType;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\ClosePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderProductRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Business;
use App\Models\CatalogItemVariant;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Peso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaseOrderController extends Controller
{
    private ?Business $currentBusiness = null;

    private bool $currentBusinessResolved = false;

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: PurchaseOrderStatus::Draft->value;

        $request->merge(['tab' => $tab]);

        $filters = $request->validate([
            'tab' => ['required', Rule::enum(PurchaseOrderStatus::class)],
        ]);

        $status = PurchaseOrderStatus::from($filters['tab']);

        return Inertia::render('Inventory/PurchaseOrders/Index', [
            'purchaseOrders' => PurchaseOrder::query()
                ->with([
                    'supplier',
                    'branch',
                    'items.variant.catalogItem',
                    'receipts.items.purchaseOrderItem.variant.catalogItem',
                ])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (PurchaseOrder $order): array => $this->detail($order))
                ->all(),
            'filters' => [
                'tab' => $status->value,
            ],
            'tabs' => collect(PurchaseOrderStatus::cases())->map(fn (PurchaseOrderStatus $tabStatus): array => [
                'value' => $tabStatus->value,
                'label' => $tabStatus->label(),
            ])->all(),
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

        return back()->with('status', 'Purchase order confirmed.');
    }

    public function close(
        ClosePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        ClosePurchaseOrder $closePurchaseOrder,
    ): RedirectResponse {
        $closePurchaseOrder->handle($purchaseOrder, $request->validated('reason'));

        return back()->with('status', 'Purchase order closed.');
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder): View|SymfonyResponse
    {
        $purchaseOrder->load(['supplier', 'branch', 'items.variant.catalogItem']);

        $business = $this->businessInformation($purchaseOrder->branch);
        $business['logo_data_uri'] = Business::current()?->logoDataUri();
        $data = [
            'purchaseOrder' => $purchaseOrder,
            'business' => $business,
            'appName' => config('app.name'),
            'forDownload' => $request->boolean('download'),
        ];

        if ($request->boolean('download')) {
            return Pdf::loadView('inventory.purchase-orders.print', $data)
                ->download($purchaseOrder->number.'.pdf');
        }

        return view('inventory.purchase-orders.print', $data);
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
                ->get(['id', 'name', 'tin', 'address']),
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

    private function currentBusiness(): ?Business
    {
        if (! $this->currentBusinessResolved) {
            $this->currentBusiness = Business::query()->first();
            $this->currentBusinessResolved = true;
        }

        return $this->currentBusiness;
    }

    /**
     * @return array{registered_business_name: ?string, address: ?string, phone: ?string, email: ?string, tin: ?string, logo_url: ?string}
     */
    private function businessInformation(?Branch $branch): array
    {
        $profile = $this->currentBusiness();

        if ($profile instanceof Business && filled($profile->name)) {
            return [
                'registered_business_name' => $profile->name,
                'address' => $profile->address,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'tin' => $profile->tin,
                'logo_url' => $profile->logoUrl(),
            ];
        }

        return [
            'registered_business_name' => $branch?->registered_business_name,
            'address' => $branch?->address,
            'phone' => null,
            'email' => null,
            'tin' => $branch?->tin,
            'logo_url' => null,
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
            'closed_at' => $order->closed_at?->toDayDateTimeString(),
            'closed_reason' => $order->closed_reason,
            'is_draft' => $order->isDraft(),
            'can_close' => ! $order->isClosed(),
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
            'business' => $this->businessInformation($order->branch),
            'receipts' => $order->receiptHistory(),
            'supplier_detail' => [
                'name' => $order->supplier?->name,
                'tin' => $order->supplier?->tin,
                'address' => $order->supplier?->address,
            ],
        ];
    }
}
