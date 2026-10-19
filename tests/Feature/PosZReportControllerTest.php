<?php

namespace Tests\Feature;

use App\Models\PosShift;
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
}
