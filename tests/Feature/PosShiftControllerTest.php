<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PosShift;
use App\Support\CashDenominations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_a_shift(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->post("/backoffice/pos/register/{$branch->id}/shifts", [
            'denominations' => $this->denominations('1000.00', 1),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('pos_shifts', 0);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/shifts", []);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'denominations' => 'Enter the cash breakdown.',
        ]);
    }

    public function test_shift_can_be_opened_with_starting_cash(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/shifts", [
                'denominations' => $this->denominations('1000.00', 2),
            ]);

        $response->assertRedirect(route('pos.index', $branch));
        $response->assertSessionHas('status', 'Shift opened.');
        $response->assertSessionHas('open_drawer', true);
        $this->assertDatabaseHas('pos_shifts', [
            'branch_id' => $branch->id,
            'starting_cash' => '2000.00',
            'opened_by_employee_number' => '10001',
            'closed_at' => null,
        ]);
    }

    public function test_a_second_open_shift_for_the_same_branch_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/shifts", [
                'denominations' => $this->denominations('1000.00', 1),
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors([
            'branch_id' => 'This branch already has an open shift.',
        ]);
        $this->assertDatabaseCount('pos_shifts', 1);
    }

    public function test_another_employee_can_close_the_shift(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
            'opened_by_employee_number' => '10001',
            'opened_by_name' => 'Sample Employee',
        ]);

        $response = $this->withSession([
            'employee' => [
                'employee_number' => '20002',
                'name' => 'Night Cashier',
            ],
        ])->from("/backoffice/pos/register/{$branch->id}")->post("/backoffice/pos/register/{$branch->id}/shifts/{$shift->id}/close", [
            'denominations' => $this->denominations('1000.00', 1),
            'remit_amount' => '1000.00',
        ]);

        $response->assertRedirect(route('pos.shifts.z-report', [
            'branch' => $branch,
            'pos_shift' => $shift,
            'print' => 1,
        ]));
        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shift->id,
            'closed_by_employee_number' => '20002',
            'closed_by_name' => 'Night Cashier',
            'counted_cash' => '1000.00',
            'remit_amount' => '1000.00',
        ]);
    }

    public function test_incomplete_cash_count_requires_confirmation(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
        ]);

        $response = $this->actingAsEmployee()
            ->from("/backoffice/pos/register/{$branch->id}")
            ->post("/backoffice/pos/register/{$branch->id}/shifts/{$shift->id}/close", [
                'denominations' => $this->denominations('1000.00', 0),
                'remit_amount' => '0.00',
            ]);

        $response->assertRedirect("/backoffice/pos/register/{$branch->id}");
        $response->assertSessionHasErrors('counted_cash');
        $this->assertDatabaseHas('pos_shifts', [
            'id' => $shift->id,
            'closed_at' => null,
        ]);
    }

    public function test_json_close_returns_the_z_report_without_printing_again(): void
    {
        $branch = Branch::factory()->create();
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'starting_cash' => 1000,
        ]);

        $response = $this->actingAsEmployee()->postJson("/backoffice/pos/register/{$branch->id}/shifts/{$shift->id}/close", [
            'denominations' => $this->denominations('1000.00', 1),
            'remit_amount' => '1000.00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'Shift closed.');
        $response->assertJsonPath('open_drawer', true);
        $this->assertStringContainsString('/z-report', (string) $response->json('z_report_url'));
        $this->assertStringContainsString('closed=1', (string) $response->json('z_report_url'));
        $this->assertStringNotContainsString('print=1', (string) $response->json('z_report_url'));
        $this->assertNotNull($shift->fresh()->closed_at);
    }

    /**
     * @return list<array{denomination: string, quantity: int}>
     */
    private function denominations(string $value, int $quantity): array
    {
        return array_map(
            fn (string $denomination): array => [
                'denomination' => $denomination,
                'quantity' => $denomination === $value ? $quantity : 0,
            ],
            CashDenominations::values(),
        );
    }
}
