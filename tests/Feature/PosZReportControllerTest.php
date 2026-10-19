<?php

namespace Tests\Feature;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use App\Models\PosShift;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosZReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_a_z_report(): void
    {
        $shift = PosShift::factory()->closed()->create();

        $this->get("/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report")->assertRedirect(route('login'));
    }

    public function test_open_shift_z_report_is_not_found(): void
    {
        $shift = PosShift::factory()->create();

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report")
            ->assertNotFound();
    }

    public function test_closed_shift_z_report_can_be_viewed(): void
    {
        $shift = PosShift::factory()->closed()->create([
            'starting_cash' => 1000,
            'counted_cash' => 1000,
            'expected_cash' => 1000,
            'variance' => 0,
            'remit_amount' => 1000,
        ]);

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report")
            ->assertOk()
            ->assertSee('Z-Report')
            ->assertSee('Amount to remit');
    }

    public function test_closed_shift_report_hides_the_print_button(): void
    {
        $shift = PosShift::factory()->closed()->create();

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report?closed=1")
            ->assertOk()
            ->assertSee('data-autoprint="0"', false)
            ->assertDontSee('id="print-z-report"', false);
    }

    public function test_print_view_includes_the_assigned_branch_receipt_printer(): void
    {
        $shift = PosShift::factory()->closed()->create();
        Printer::factory()->bluetooth()->for($shift->branch)->create([
            'name' => 'Front receipt',
            'role' => PrinterRole::Receipt,
            'connection_type' => PrinterConnection::Bluetooth,
            'is_default' => true,
            'bluetooth_id' => 'bt-z-report',
            'device_name' => 'RPP02N',
        ]);
        Printer::factory()->bluetooth()->create([
            'name' => 'Other branch',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
            'bluetooth_id' => 'bt-other',
        ]);

        $this->actingAsEmployee()
            ->get("/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report?print=1")
            ->assertOk()
            ->assertSee('data-autoprint="1"', false)
            ->assertDontSee('id="print-z-report"', false)
            ->assertSee('bt-z-report', false)
            ->assertSee('RPP02N', false)
            ->assertSee('Z-REPORT', false)
            ->assertDontSee('bt-other', false);
    }

    public function test_json_print_payload_uses_the_shift_branch_receipt_printer(): void
    {
        $shift = PosShift::factory()->closed()->create();
        Printer::factory()->bluetooth()->for($shift->branch)->create([
            'name' => 'Front receipt',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
            'bluetooth_id' => 'bt-z-report',
            'device_name' => 'RPP02N',
        ]);
        Printer::factory()->bluetooth()->create([
            'name' => 'Other branch',
            'role' => PrinterRole::Receipt,
            'is_default' => true,
            'bluetooth_id' => 'bt-other',
        ]);

        $response = $this->actingAsEmployee()->getJson(
            "/backoffice/pos/register/{$shift->branch_id}/shifts/{$shift->id}/z-report?print=1",
        );

        $response->assertOk();
        $response->assertJsonPath('printer.bluetooth_id', 'bt-z-report');
        $response->assertJsonPath('printer.device_name', 'RPP02N');
        $response->assertJsonPath('printer.connection', 'bluetooth');
        $this->assertStringContainsString('Z-REPORT', (string) $response->json('receipt'));
        $this->assertStringContainsString((string) $shift->branch?->name, (string) $response->json('receipt'));
        $this->assertNotSame('bt-other', $response->json('printer.bluetooth_id'));
    }
}
