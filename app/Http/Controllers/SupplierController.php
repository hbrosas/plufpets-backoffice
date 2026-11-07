<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inventory/Suppliers/Index', [
            'suppliers' => Supplier::query()
                ->withCount('purchaseOrders')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'tin' => $supplier->tin,
                    'code' => $supplier->code,
                    'contact_name' => $supplier->contact_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'address' => $supplier->address,
                    'notes' => $supplier->notes,
                    'is_active' => $supplier->is_active,
                    'purchase_order_count' => $supplier->purchase_orders_count,
                ]),
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        $supplier->load(['purchaseOrders' => function ($query): void {
            $query->with('items')->orderByDesc('ordered_at')->orderByDesc('id');
        }]);

        return Inertia::render('Inventory/Suppliers/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'tin' => $supplier->tin,
                'code' => $supplier->code,
                'contact_name' => $supplier->contact_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
                'is_active' => $supplier->is_active,
                'purchase_orders' => $supplier->purchaseOrders->map(fn (PurchaseOrder $order): array => [
                    'id' => $order->id,
                    'number' => $order->number,
                    'status_label' => $order->status->label(),
                    'ordered_at' => $order->ordered_at?->toDayDateTimeString(),
                    'total' => Peso::format($order->totalAmount()),
                ])->all(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->safe()->only([
            'name',
            'tin',
            'code',
            'contact_name',
            'email',
            'phone',
            'address',
            'notes',
            'is_active',
        ]));

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('status', 'Supplier created.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->safe()->only([
            'name',
            'tin',
            'code',
            'contact_name',
            'email',
            'phone',
            'address',
            'notes',
            'is_active',
        ]));

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return redirect()
                ->route('inventory.suppliers.index')
                ->with('status', 'This supplier has purchase orders, so it cannot be deleted.');
        }

        $supplier->delete();

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('status', 'Supplier deleted.');
    }
}
