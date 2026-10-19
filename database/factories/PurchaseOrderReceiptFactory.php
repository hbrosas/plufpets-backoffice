<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderReceipt>
 */
class PurchaseOrderReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory()->confirmed(),
            'delivery_receipt_number' => 'DR-'.fake()->numerify('####'),
            'sales_invoice_number' => 'SI-'.fake()->numerify('####'),
            'received_by_employee_number' => '10001',
            'received_by_name' => 'Sample Employee',
            'received_at' => now(),
        ];
    }
}
