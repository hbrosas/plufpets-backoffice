<?php

namespace Tests\Unit;

use App\Support\SaleReceipt;
use PHPUnit\Framework\TestCase;

class SaleReceiptTest extends TestCase
{
    public function test_receipt_lists_the_sale_items_total_tendered_change_and_payments(): void
    {
        $text = SaleReceipt::text([
            'sold_at' => 'Oct 19, 2026 2:46 PM',
            'number' => 'POS-20261019-0001',
            'items' => [
                ['name' => 'Kibble Large', 'quantity' => 2, 'subtotal' => '₱200.00'],
            ],
            'total' => '₱200.00',
            'tendered' => '₱1,000.00',
            'change' => '₱800.00',
            'payments' => [
                ['name' => 'Cash', 'reference' => null],
                ['name' => 'GCash', 'reference' => '998877'],
            ],
        ], 48);

        $this->assertStringContainsString('Oct 19, 2026 2:46 PM - POS-20261019-0001', $text);
        $this->assertStringContainsString('Kibble Large', $text);
        $this->assertStringContainsString('2  P200.00', $text);
        $this->assertStringContainsString('Total', $text);
        $this->assertStringContainsString('Tendered Amount', $text);
        $this->assertStringContainsString('P1,000.00', $text);
        $this->assertStringContainsString('Change', $text);
        $this->assertStringContainsString('P800.00', $text);
        $this->assertStringContainsString('Cash', $text);
        $this->assertStringContainsString('GCash - 998877', $text);
        $this->assertStringNotContainsString('₱', $text);
        $this->assertFalse(str_ends_with($text, "\n"));
    }
}
