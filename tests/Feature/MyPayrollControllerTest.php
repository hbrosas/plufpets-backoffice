<?php

namespace Tests\Feature;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\PayrollFiling;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MyPayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_my_payroll(): void
    {
        $this->get('/backoffice/me/payroll')->assertRedirect(route('login'));
        $this->post('/backoffice/me/payroll')->assertRedirect(route('login'));
    }

    public function test_employee_files_leave_overtime_and_overnight(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)->post('/backoffice/me/payroll', [
            'type' => 'leave',
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-12',
            'reason' => 'Family day',
        ])->assertRedirect();

        $this->actingAsEmployee($employee)->post('/backoffice/me/payroll', [
            'type' => 'overtime',
            'starts_on' => '2026-11-10',
            'hours' => '2.5',
            'reason' => 'Late grooming',
        ])->assertRedirect();

        $this->actingAsEmployee($employee)->post('/backoffice/me/payroll', [
            'type' => 'overnight',
            'starts_on' => '2026-11-11',
            'nights' => 1,
            'reason' => 'Boarding',
        ])->assertRedirect();

        $leave = PayrollFiling::query()->where('employee_id', $employee->id)->where('type', 'leave')->firstOrFail();
        $this->assertSame('2026-11-12', $leave->ends_on->toDateString());
        $this->assertSame(PayrollFilingStatus::Pending, $leave->status);
        $this->assertDatabaseHas('payroll_filings', [
            'employee_id' => $employee->id,
            'type' => 'overtime',
            'hours' => '2.50',
        ]);
        $this->assertDatabaseHas('payroll_filings', [
            'employee_id' => $employee->id,
            'type' => 'overnight',
            'nights' => 1,
        ]);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/payroll')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Me/Payroll')
                ->has('filings', 3));
    }

    public function test_employee_requests_a_cash_advance(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 02:00:00', 'UTC'));
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->post('/backoffice/me/payroll', [
                'type' => 'cash_advance',
                'amount' => '1500.50',
                'reason' => 'Medicine',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Cash advance filed.');

        $filing = PayrollFiling::query()->whereBelongsTo($employee)->firstOrFail();
        $this->assertSame(PayrollFilingType::CashAdvance, $filing->type);
        $this->assertSame(PayrollFilingStatus::Pending, $filing->status);
        $this->assertSame('1500.50', $filing->amount);
        $this->assertSame('2026-11-07', $filing->starts_on->toDateString());
        $this->assertSame('Medicine', $filing->reason);
    }

    public function test_cash_advance_requires_an_amount_and_a_reason(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->from('/backoffice/me/payroll')
            ->post('/backoffice/me/payroll', [
                'type' => 'cash_advance',
            ])
            ->assertRedirect('/backoffice/me/payroll')
            ->assertSessionHasErrors([
                'amount' => 'Enter the cash advance amount.',
                'reason' => 'Enter a reason.',
            ]);
    }

    #[TestWith(['0', 'Cash advance must be more than zero.'])]
    #[TestWith(['10000000', 'Cash advance cannot be more than 9,999,999.99.'])]
    #[TestWith(['10.999', 'Cash advance can have at most 2 decimal places.'])]
    public function test_cash_advance_rejects_an_invalid_amount(string $amount, string $message): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->from('/backoffice/me/payroll')
            ->post('/backoffice/me/payroll', [
                'type' => 'cash_advance',
                'amount' => $amount,
                'reason' => 'Medicine',
            ])
            ->assertRedirect('/backoffice/me/payroll')
            ->assertSessionHasErrors(['amount' => $message]);
    }

    public function test_overtime_requires_hours(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->from('/backoffice/me/payroll')
            ->post('/backoffice/me/payroll', [
                'type' => 'overtime',
                'starts_on' => '2026-11-10',
            ])
            ->assertRedirect('/backoffice/me/payroll')
            ->assertSessionHasErrors('hours');
    }

    public function test_employee_withdraws_a_pending_filing_and_cannot_withdraw_an_approved_one(): void
    {
        $employee = Employee::factory()->create();
        $pending = PayrollFiling::factory()->create(['employee_id' => $employee->id]);
        $approved = PayrollFiling::factory()->overtime()->create([
            'employee_id' => $employee->id,
            'status' => PayrollFilingStatus::Approved,
        ]);

        $this->actingAsEmployee($employee)
            ->delete('/backoffice/me/payroll/'.$pending->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('payroll_filings', ['id' => $pending->id]);

        $this->actingAsEmployee($employee)
            ->delete('/backoffice/me/payroll/'.$approved->id)
            ->assertNotFound();
    }

    public function test_employee_downloads_only_their_pay_slip(): void
    {
        $employee = Employee::factory()->create();
        $own = EmployeePayroll::factory()->create(['employee_id' => $employee->id]);
        $snapshot = $own->snapshot;
        $snapshot['payroll']['days_worked'] = '10';
        $own->update(['snapshot' => $snapshot]);
        $other = EmployeePayroll::factory()->create();

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/payrolls/'.$own->id.'/download')
            ->assertOk();

        $this->actingAsEmployee($employee)
            ->get('/backoffice/me/payrolls/'.$other->id.'/download')
            ->assertNotFound();
    }
}
