<?php

namespace Tests\Feature;

use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_record_cash_movement(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/cash-movements", [
            'type' => PosCashMovementType::CashIn->value,
            'amount' => '50',
            'reason' => 'Change fund',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/cash-movements", []);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'type' => 'Choose cash in or cash out.',
            'amount' => 'Enter an amount.',
            'reason' => 'Enter a reason.',
        ]);
    }

    public function test_cash_out_is_recorded(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/cash-movements", [
                'type' => PosCashMovementType::CashOut->value,
                'amount' => '200',
                'reason' => 'Petty cash',
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('open_drawer', true);
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashOut->value,
            'amount' => '200.00',
            'reason' => 'Petty cash',
        ]);
    }
}
