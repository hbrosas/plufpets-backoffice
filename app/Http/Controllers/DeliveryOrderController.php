<?php

namespace App\Http\Controllers;

use App\Actions\ReceivePurchaseOrder;
use App\Enums\DeliveryStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Support\EmployeeSession;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: DeliveryStatus::ForDelivery->value;

        $request->merge(['tab' => $tab]);

        $filters = $request->validate([
            'tab' => ['required', Rule::enum(DeliveryStatus::class)],
        ]);

        $status = DeliveryStatus::from($filters['tab']);

        $orders = PurchaseOrder::query()
            ->confirmed()
            ->with(['supplier:id,name', 'items'])
            ->when($status === DeliveryStatus::ForDelivery, function ($query): void {
                $query->whereDoesntHave('items', fn ($query) => $query->where('received_quantity', '>', 0));
            })
            ->when($status === DeliveryStatus::PartiallyDelivered, function ($query): void {
                $query->whereHas('items', fn ($query) => $query->where('received_quantity', '>', 0))
                    ->whereHas('items', fn ($query) => $query->whereColumn('received_quantity', '<', 'quantity'));
            })
            ->when($status === DeliveryStatus::Completed, function ($query): void {
                $query->whereDoesntHave('items', fn ($query) => $query->whereColumn('received_quantity', '<', 'quantity'));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier' => $order->supplier?->name,
                'ordered_quantity' => $order->orderedQuantity(),
                'received_quantity' => $order->receivedQuantity(),
                'delivery_status' => $order->deliveryStatus()->value,
                'delivery_status_label' => $order->deliveryStatus()->label(),
            ]);

        return Inertia::render('Inventory/DeliveryOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'tab' => $status->value,
            ],
            'tabs' => collect(DeliveryStatus::cases())->map(fn (DeliveryStatus $tab): array => [
                'value' => $tab->value,
                'label' => $tab->label(),
            ]),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::Confirmed, 404);

        $purchaseOrder->load([
            'supplier',
            'items.variant.catalogItem',
            'receipts.items.purchaseOrderItem.variant.catalogItem',
        ]);

        $employee = EmployeeSession::current();

        return Inertia::render('Inventory/DeliveryOrders/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplier' => $purchaseOrder->supplier?->name,
                'ordered_quantity' => $purchaseOrder->orderedQuantity(),
                'received_quantity' => $purchaseOrder->receivedQuantity(),
                'total' => Peso::format($purchaseOrder->totalAmount()),
                'delivery_status' => $purchaseOrder->deliveryStatus()->value,
                'delivery_status_label' => $purchaseOrder->deliveryStatus()->label(),
                'receiver' => $employee === null ? null : [
                    'name' => $employee['name'],
                    'employee_number' => $employee['employee_number'],
                ],
                'items' => $purchaseOrder->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'product' => $item->variant?->catalogItem?->name,
                    'variant' => $item->variant?->name,
                    'sku' => $item->variant?->sku,
                    'quantity' => $item->quantity,
                    'received_quantity' => $item->received_quantity,
                    'remaining' => $item->remainingToReceive(),
                ])->all(),
                'receipts' => $purchaseOrder->receiptHistory(),
            ],
        ]);
    }

    public function receive(
        ReceivePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        ReceivePurchaseOrder $receivePurchaseOrder,
    ): RedirectResponse {
        $employee = EmployeeSession::current();

        abort_unless($employee !== null, 403);

        $receivePurchaseOrder->handle($purchaseOrder, $request->validated('items'), [
            'delivery_receipt_number' => $request->validated('delivery_receipt_number'),
            'sales_invoice_number' => $request->validated('sales_invoice_number'),
            'received_by_employee_number' => $employee['employee_number'],
            'received_by_name' => $employee['name'],
        ]);

        return redirect()
            ->route('inventory.delivery-orders.show', $purchaseOrder)
            ->with('status', 'Delivery recorded.');
    }
}
