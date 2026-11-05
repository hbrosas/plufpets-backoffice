<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PosSaleItemCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyCommissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_my_commissions(): void
    {
        $this->get('/backoffice/me/commissions')->assertRedirect(route('login'));
    }

    public function test_employee_sees_only_their_commissions(): void
    {
        $employee = Employee::factory()->create(['name' => 'Ada Groomer']);
        $other = Employee::factory()->create(['name' => 'Ben Cashier']);
        PosSaleItemCommission::factory()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'amount' => 25,
        ]);
        PosSaleItemCommission::factory()->create([
            'employee_id' => $other->id,
            'employee_number' => $other->employee_number,
            'employee_name' => $other->name,
            'amount' => 40,
        ]);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/commissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Commissions')
                ->where('account', true)
                ->where('report.total', '₱25.00')
                ->has('lines', 1)
                ->where('lines.0.amount', '₱25.00'));
    }
}
