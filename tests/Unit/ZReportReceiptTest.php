<?php

namespace Tests\Unit;

use App\Support\ZReportReceipt;
use PHPUnit\Framework\TestCase;

class ZReportReceiptTest extends TestCase
{
    public function test_receipt_includes_branch_totals_and_peso_as_ascii(): void
    {
        $text = ZReportReceipt::text([
            'app' => 'Pluf Pets',
            'branch' => 'Greenheights',
            'opened' => 'Opened by Sample Employee · Oct 19, 2026 10:00 AM',
            'closed' => 'Closed by Night Cashier · Oct 19, 2026 6:00 PM',
            'payments' => [
                ['name' => 'Cash', 'count' => 2, 'total' => '₱150.00'],
            ],
            'item_count' => 3,
            'sales_total' => '₱150.00',
            'starting_cash' => '₱1,000.00',
            'cash_sales' => '₱150.00',
            'cash_in' => '₱0.00',
            'cash_out' => '₱0.00',
            'expected_cash' => '₱1,150.00',
            'counted_cash' => '₱1,150.00',
            'variance' => '₱0.00',
            'remit_amount' => '₱1,150.00',
        ]);

        $this->assertStringContainsString('Z-REPORT', $text);
        $this->assertStringContainsString('Greenheights', $text);
        $this->assertStringContainsString('Cash (2)', $text);
        $this->assertStringContainsString('Amount to remit', $text);
        $this->assertStringContainsString('P1,150.00', $text);
        $this->assertStringNotContainsString('₱', $text);
    }
}
