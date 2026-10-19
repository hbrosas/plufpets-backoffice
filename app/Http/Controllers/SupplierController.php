<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
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

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->safe()->only([
            'name',
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
