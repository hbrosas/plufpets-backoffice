<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_suppliers(): void
    {
        $response = $this->get('/backoffice/inventory/suppliers');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'Acme Feeds']);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/suppliers');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/Suppliers/Index')
            ->where('suppliers.0.name', 'Acme Feeds'));
    }

    public function test_empty_payload_is_rejected(): void
    {
        $response = $this->actingAsEmployee()->from('/backoffice/inventory/suppliers')->post('/backoffice/inventory/suppliers', []);

        $response->assertRedirect('/backoffice/inventory/suppliers');
        $response->assertSessionHasErrors(['name' => 'Enter a supplier name.']);
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_supplier_is_created(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/inventory/suppliers', [
            'name' => 'Acme Feeds',
            'code' => 'ACME',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('inventory.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Feeds',
            'code' => 'ACME',
        ]);
    }

    public function test_supplier_with_purchase_orders_is_not_deleted(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Acme Feeds']);
        PurchaseOrder::factory()->for($supplier)->create();

        $response = $this->actingAsEmployee()->delete("/backoffice/inventory/suppliers/{$supplier->id}");

        $response->assertRedirect(route('inventory.suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
