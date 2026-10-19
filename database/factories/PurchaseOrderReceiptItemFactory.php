<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderReceiptItem>
 */
class PurchaseOrderReceiptItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_receipt_id' => PurchaseOrderReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
