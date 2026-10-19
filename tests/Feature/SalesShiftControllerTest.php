<?php

namespace Tests\Feature;

use App\Enums\PosCashMovementType;
use App\Enums\PrinterRole;
use App\Models\Branch;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_sales_shifts(): void
    {
        $response = $this->get('/backoffice/sales/shifts');

        $response->assertRedirect(route('login'));
    }

    public function test_shifts_list_includes_sales_totals_and_closed_z_report_link(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $open = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'opened_at' => now(),
        ]);
        $closed = PosShift::factory()->closed()->create([
            'branch_id' => $branch->id,
            'opened_at' => now(),
            'closed_at' => now(),
        ]);
        Printer::factory()->bluetooth()->for($branch)->create([
            'name' => 'Front receipt',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
            'bluetooth_id' => 'bt-greenheights',
            'device_name' => 'Y58_2052',
        ]);
        PosSale::factory()->create([
            'pos_shift_id' => $closed->id,
            'branch_id' => $branch->id,
            'number' => 'POS-SHIFT-1',
            'total' => 250,
            'sold_at' => now(),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $closed->id,
            'type' => PosCashMovementType::CashOut,
            'amount' => 20,
            'reason' => 'Change fund',
            'recorded_at' => now()->subMinutes(5),
        ]);
        $recent = PosShift::factory()->closed()->create([
            'branch_id' => $branch->id,
            'opened_at' => now()->subDays(8),
            'closed_at' => now()->subDays(8),
        ]);
        PosShift::factory()->closed()->create([
            'branch_id' => $branch->id,
            'opened_at' => now()->subDays(40),
            'closed_at' => now()->subDays(40),
        ]);

        $response = $this->actingAsEmployee()->get('/backoffice/sales/shifts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Shifts/Index')
            ->where('filters.preset', '30d')
            ->has('shifts.data', 3)
            ->where('shifts.data.2.id', $recent->id)
            ->where('shifts.data.0.id', $closed->id)
            ->where('shifts.data.0.branch', 'Greenheights')
            ->where('shifts.data.0.status', 'Closed')
            ->where('shifts.data.0.receipts', 1)
            ->where('shifts.data.0.sales_total', '₱250.00')
            ->where('shifts.data.0.z_report_href', '/backoffice/pos/register/'.$branch->id.'/shifts/'.$closed->id.'/z-report')
            ->where('shifts.data.0.z_report_print_href', '/backoffice/pos/register/'.$branch->id.'/shifts/'.$closed->id.'/z-report?print=1')
            ->where('shifts.data.0.receipt_printer.bluetooth_id', 'bt-greenheights')
            ->where('shifts.data.0.receipt_printer.device_name', 'Y58_2052')
            ->where('shifts.data.0.transactions.0.title', 'POS-SHIFT-1')
            ->where('shifts.data.0.transactions.0.type', 'Sale')
            ->where('shifts.data.1.id', $open->id)
            ->where('shifts.data.1.status', 'Open')
            ->where('shifts.data.1.z_report_href', null)
            ->where('shifts.data.1.z_report_print_href', null));
    }
}
