<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TestingModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_mode_can_be_turned_on(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/business', [
            'name' => 'Pluf Pets',
            'testing_mode' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Business information saved.');
        $this->assertDatabaseHas('businesses', [
            'name' => 'Pluf Pets',
            'testing_mode' => true,
        ]);
    }

    public function test_branch_can_be_marked_for_test_use(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/shop/branches', [
            'name' => 'Training Branch',
            'code' => 'TRN',
            'is_active' => true,
            'for_test_use' => true,
        ]);

        $response->assertRedirect(route('shop.branches.index'));
        $this->assertDatabaseHas('branches', [
            'name' => 'Training Branch',
            'code' => 'TRN',
            'for_test_use' => true,
        ]);
    }

    public function test_supplier_can_be_marked_for_test_use(): void
    {
        $response = $this->actingAsEmployee()->post('/backoffice/inventory/suppliers', [
            'name' => 'Training Supplier',
            'code' => 'TRN',
            'is_active' => true,
            'for_test_use' => true,
        ]);

        $response->assertRedirect(route('inventory.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Training Supplier',
            'code' => 'TRN',
            'for_test_use' => true,
        ]);
    }

    public function test_test_branch_sales_are_hidden_when_testing_mode_is_off(): void
    {
        $this->recordSale('POS-REAL', false);
        $this->recordSale('POS-TEST', true);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('receipts.data', 1)
            ->where('receipts.data.0.number', 'POS-REAL'));
    }

    public function test_test_branch_sales_are_listed_when_testing_mode_is_on(): void
    {
        Business::factory()->create(['testing_mode' => true]);
        $this->recordSale('POS-REAL', false);
        $this->recordSale('POS-TEST', true);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/receipts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('receipts.data', 2)
            ->where('receipts.data.0.number', 'POS-TEST')
            ->where('receipts.data.1.number', 'POS-REAL'));
    }

    public function test_purchase_orders_for_test_data_are_hidden_when_testing_mode_is_off(): void
    {
        $this->purchaseOrder('PO-REAL');
        $this->purchaseOrder('PO-TEST-SUPPLIER', supplierIsTest: true);
        $this->purchaseOrder('PO-TEST-BRANCH', branchIsTest: true);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/purchase-orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('purchaseOrders', 1)
            ->where('purchaseOrders.0.number', 'PO-REAL'));
    }

    public function test_purchase_orders_for_test_data_are_listed_when_testing_mode_is_on(): void
    {
        Business::factory()->create(['testing_mode' => true]);
        $this->purchaseOrder('PO-REAL');
        $this->purchaseOrder('PO-TEST-SUPPLIER', supplierIsTest: true);
        $this->purchaseOrder('PO-TEST-BRANCH', branchIsTest: true);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/purchase-orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('purchaseOrders', 3)
            ->where('purchaseOrders.0.number', 'PO-TEST-BRANCH')
            ->where('purchaseOrders.1.number', 'PO-TEST-SUPPLIER')
            ->where('purchaseOrders.2.number', 'PO-REAL'));
    }

    public function test_stock_assignment_hides_test_data_when_testing_mode_is_off(): void
    {
        $real = $this->receivedItem('Real Shop', false, 'Real Supplier', false);
        $this->receivedItem('Test Shop', true, 'Test Supplier', true);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/stock-assignments');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('branches', 1)
            ->where('branches.0.name', 'Real Shop')
            ->has('items', 1)
            ->where('items.0.id', $real->id));
    }

    public function test_stock_assignment_includes_test_data_when_testing_mode_is_on(): void
    {
        Business::factory()->create(['testing_mode' => true]);
        $real = $this->receivedItem('Real Shop', false, 'Real Supplier', false);
        $test = $this->receivedItem('Test Shop', true, 'Test Supplier', true);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/stock-assignments');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('branches', 2)
            ->where('branches.0.name', 'Real Shop')
            ->where('branches.1.name', 'Test Shop')
            ->has('items', 2)
            ->where('items.0.id', $test->id)
            ->where('items.1.id', $real->id));
    }

    public function test_stock_cannot_be_assigned_to_a_test_branch_when_testing_mode_is_off(): void
    {
        $branch = Branch::factory()->create(['for_test_use' => true]);
        $item = $this->receivedItem('Real Shop', false, 'Real Supplier', false);

        $response = $this->actingAsEmployee()
            ->from('/backoffice/inventory/stock-assignments')
            ->post('/backoffice/inventory/stock-assignments', [
                'branch_id' => $branch->id,
                'items' => [[
                    'purchase_order_item_id' => $item->id,
                    'quantity' => 1,
                ]],
            ]);

        $response->assertRedirect('/backoffice/inventory/stock-assignments');
        $response->assertSessionHasErrors('branch_id');
        $this->assertDatabaseCount('stock_assignments', 0);
    }

    public function test_test_branch_register_is_not_found_when_testing_mode_is_off(): void
    {
        $branch = Branch::factory()->create(['for_test_use' => true]);

        $response = $this->actingAsEmployee()->get('/backoffice/pos/register/'.$branch->id);

        $response->assertNotFound();
    }

    public function test_supplier_with_a_hidden_purchase_order_is_not_deleted(): void
    {
        $supplier = Supplier::factory()->create(['for_test_use' => true]);
        PurchaseOrder::factory()->for($supplier)->create();

        $response = $this->actingAsEmployee()->delete('/backoffice/inventory/suppliers/'.$supplier->id);

        $response->assertRedirect(route('inventory.suppliers.index'));
        $this->assertModelExists($supplier);
    }

    private function recordSale(string $number, bool $branchIsTest): void
    {
        $branch = Branch::factory()->create(['for_test_use' => $branchIsTest]);
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);

        PosSale::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
            'number' => $number,
            'sold_at' => now(),
        ]);
    }

    private function purchaseOrder(string $number, bool $supplierIsTest = false, bool $branchIsTest = false): void
    {
        PurchaseOrder::factory()->create([
            'number' => $number,
            'supplier_id' => Supplier::factory()->create(['for_test_use' => $supplierIsTest])->id,
            'branch_id' => Branch::factory()->create(['for_test_use' => $branchIsTest])->id,
        ]);
    }

    private function receivedItem(string $branchName, bool $branchIsTest, string $supplierName, bool $supplierIsTest): PurchaseOrderItem
    {
        $branch = Branch::factory()->create([
            'name' => $branchName,
            'for_test_use' => $branchIsTest,
        ]);
        $supplier = Supplier::factory()->create([
            'name' => $supplierName,
            'for_test_use' => $supplierIsTest,
        ]);
        $order = PurchaseOrder::factory()->confirmed()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
        ]);
        $catalogItem = CatalogItem::factory()->create();
        $variant = CatalogItemVariant::factory()->for($catalogItem)->create();

        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 4,
            'received_quantity' => 4,
        ]);
    }
}
