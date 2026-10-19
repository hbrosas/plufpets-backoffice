<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_delivery_orders(): void
    {
        $response = $this->get('/backoffice/inventory/delivery-orders');

        $response->assertRedirect(route('login'));
    }

    public function test_confirmed_purchase_orders_appear_for_delivery(): void
    {
        $order = $this->confirmedOrder(quantity: 8);

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/delivery-orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/DeliveryOrders/Index')
            ->where('filters.tab', DeliveryStatus::ForDelivery->value)
            ->where('orders.data.0.number', $order->number)
            ->where('orders.data.0.ordered_quantity', 8));
    }

    public function test_draft_purchase_orders_do_not_appear(): void
    {
        PurchaseOrder::factory()->create();

        $response = $this->actingAsEmployee()->get('/backoffice/inventory/delivery-orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
    }

    public function test_partial_receipt_moves_the_order_to_partially_delivered(): void
    {
        $this->freezeTime();

        $order = $this->confirmedOrder(quantity: 10);
        $item = $order->items()->first();

        $response = $this->actingAsEmployee()->post("/backoffice/inventory/delivery-orders/{$order->id}/receive", [
            'delivery_receipt_number' => 'DR-88',
            'sales_invoice_number' => 'SI-12',
            'items' => [[
                'id' => $item->id,
                'quantity' => 4,
            ]],
        ]);

        $response->assertRedirect(route('inventory.delivery-orders.show', $order));
        $this->assertSame(4, $item->fresh()->received_quantity);
        $this->assertDatabaseHas('purchase_order_receipts', [
            'purchase_order_id' => $order->id,
            'delivery_receipt_number' => 'DR-88',
            'sales_invoice_number' => 'SI-12',
            'received_by_name' => 'Sample Employee',
            'received_by_employee_number' => '10001',
            'received_at' => now(),
        ]);
        $this->assertDatabaseHas('purchase_order_receipt_items', [
            'purchase_order_item_id' => $item->id,
            'quantity' => 4,
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/inventory/delivery-orders?tab=partially_delivered')
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $order->number));
    }

    public function test_full_receipt_completes_the_delivery(): void
    {
        $order = $this->confirmedOrder(quantity: 6);
        $item = $order->items()->first();

        $this->actingAsEmployee()->post("/backoffice/inventory/delivery-orders/{$order->id}/receive", [
            'items' => [[
                'id' => $item->id,
                'quantity' => 6,
            ]],
        ])->assertRedirect(route('inventory.delivery-orders.show', $order));

        $this->actingAsEmployee()
            ->get('/backoffice/inventory/delivery-orders?tab=completed')
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.number', $order->number));
    }

    public function test_over_receiving_is_rejected(): void
    {
        $order = $this->confirmedOrder(quantity: 3);
        $item = $order->items()->first();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/inventory/delivery-orders/{$order->id}")
            ->post("/backoffice/inventory/delivery-orders/{$order->id}/receive", [
                'items' => [[
                    'id' => $item->id,
                    'quantity' => 4,
                ]],
            ]);

        $response->assertRedirect("/backoffice/inventory/delivery-orders/{$order->id}");
        $response->assertSessionHasErrors(['items.0.quantity']);
        $this->assertSame(0, $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_order_receipts', 0);
    }

    public function test_delivery_show_page_lists_the_logged_in_receiver_and_history(): void
    {
        $this->freezeTime();

        $order = $this->confirmedOrder(quantity: 6);
        $item = $order->items()->first();

        $this->actingAsEmployee()->post("/backoffice/inventory/delivery-orders/{$order->id}/receive", [
            'delivery_receipt_number' => 'DR-01',
            'items' => [[
                'id' => $item->id,
                'quantity' => 6,
            ]],
        ]);

        $response = $this->actingAsEmployee()->get("/backoffice/inventory/delivery-orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inventory/DeliveryOrders/Show')
            ->where('order.receiver.name', 'Sample Employee')
            ->where('order.receiver.employee_number', '10001')
            ->where('order.receipts.0.delivery_receipt_number', 'DR-01')
            ->where('order.receipts.0.received_by', 'Sample Employee')
            ->where('order.receipts.0.items.0.quantity', 6));
        $this->assertNotNull(PurchaseOrderReceipt::query()->first()?->received_at);
    }

    private function confirmedOrder(int $quantity): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->confirmed()->create();
        $item = CatalogItem::factory()->create(['name' => 'Organic kibble']);
        $variant = CatalogItemVariant::factory()->for($item)->create();

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'catalog_item_variant_id' => $variant->id,
            'quantity' => $quantity,
            'received_quantity' => 0,
        ]);

        return $order->fresh(['items']);
    }
}
