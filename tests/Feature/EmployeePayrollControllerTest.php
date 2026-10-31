<?php

namespace Tests\Feature;

use App\Actions\GeneratePayroll;
use App\Enums\EmploymentStatus;
use App\Enums\HolidayType;
use App\Enums\PayBasis;
use App\Enums\PayLineKind;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Support\PayrollRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeePayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_payroll(): void
    {
        $employee = Employee::factory()->create();

        $this->post("/backoffice/staff/{$employee->id}/payroll")->assertRedirect(route('login'));
        $this->post("/backoffice/staff/{$employee->id}/payroll/finalize")->assertRedirect(route('login'));
        $this->get("/backoffice/staff/{$employee->id}/payroll/download")->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_cannot_generate_payroll(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->post("/backoffice/staff/{$employee->id}/payroll", $this->hours())
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->get("/backoffice/staff/{$employee->id}/payroll/download?".http_build_query($this->hours()))
            ->assertForbidden();
    }

    public function test_missing_profile_is_not_found(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee()
            ->post("/backoffice/staff/{$employee->id}/payroll", $this->hours())
            ->assertNotFound();

        $this->actingAsEmployee()
            ->get("/backoffice/staff/{$employee->id}/payroll/download?".http_build_query($this->hours()))
            ->assertNotFound();
    }

    public function test_empty_payroll_input_is_rejected(): void
    {
        $employee = $this->employeeWithProfile();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", []);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'period_start' => 'Enter the payroll start date.',
            'period_end' => 'Enter the payroll end date.',
            'days_worked' => 'Enter the number of days worked.',
            'late_hours' => 'Enter late hours.',
            'overtime_hours' => 'Enter overtime hours.',
        ]);
    }

    public function test_negative_late_hours_are_rejected(): void
    {
        $employee = $this->employeeWithProfile();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours(),
                'late_hours' => '-1',
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'late_hours' => 'Late hours cannot be negative.',
        ]);
    }

    public function test_days_worked_above_31_are_rejected(): void
    {
        $employee = $this->employeeWithProfile();

        $response = $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours(),
                'days_worked' => '32',
            ]);

        $response->assertRedirect("/backoffice/staff/{$employee->id}/record");
        $response->assertSessionHasErrors([
            'days_worked' => 'Days worked cannot be more than 31.',
        ]);
    }

    public function test_monthly_payroll_prorates_rates_and_deducts_late_and_contributions(): void
    {
        $employee = $this->employeeWithProfile();
        $profile = $employee->profile;
        $profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '20000',
            'sort' => 0,
        ]);
        $profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'COLA',
            'amount' => '1300',
            'sort' => 1,
        ]);
        $profile->payLines()->create([
            'kind' => PayLineKind::Benefit,
            'name' => 'Rice allowance',
            'amount' => '260',
            'sort' => 2,
        ]);
        $profile->payLines()->create([
            'kind' => PayLineKind::Government,
            'name' => 'SSS',
            'amount' => null,
            'employee_contribution' => '200',
            'employer_contribution' => '400',
            'sort' => 3,
        ]);

        $response = $this->actingAsEmployee()->post("/backoffice/staff/{$employee->id}/payroll", $this->hours());

        $response->assertRedirect(route('staff.record.show', [
            'employee' => $employee,
            ...$this->hours(),
        ]));

        $this->actingAsEmployee()
            ->get(route('staff.record.show', ['employee' => $employee, ...$this->hours()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Record')
                ->where('payroll.days_worked', '10')
                ->where('payroll.hourly_rate', '102.40')
                ->where('payroll.earnings.0.name', 'Basic salary')
                ->where('payroll.earnings.0.label', 'Regular')
                ->where('payroll.earnings.0.daily_rate', '769.23')
                ->where('payroll.earnings.0.rate', '769.23')
                ->where('payroll.earnings.0.unit', '10.00')
                ->where('payroll.earnings.0.amount', '7692.31')
                ->where('payroll.earnings.1.name', 'COLA')
                ->where('payroll.earnings.1.amount', '500.00')
                ->where('payroll.earnings.2.name', 'Rice allowance')
                ->where('payroll.earnings.2.amount', '100.00')
                ->where('payroll.earnings.3.name', 'Overtime')
                ->where('payroll.earnings.3.rate', '128.00')
                ->where('payroll.earnings.3.unit', '4.00')
                ->where('payroll.earnings.3.amount', '512.00')
                ->where('payroll.earnings.4.name', 'Commissions')
                ->where('payroll.earnings.4.amount', '0.00')
                ->where('payroll.deductions.0.rate', '102.40')
                ->where('payroll.deductions.0.unit', '2.00')
                ->where('payroll.deductions.0.name', 'Late')
                ->where('payroll.deductions.0.amount', '204.80')
                ->where('payroll.deductions.1.name', 'SSS')
                ->where('payroll.deductions.1.amount', '200.00')
                ->where('payroll.employer_shares.0.amount', '400.00')
                ->where('payroll.gross', '8804.31')
                ->where('payroll.total_deductions', '404.80')
                ->where('payroll.net', '8399.51'));
    }

    public function test_daily_payroll_multiplies_the_daily_rate_by_days_worked(): void
    {
        $employee = $this->employeeWithProfile(PayBasis::Day);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '500',
            'sort' => 0,
        ]);

        $this->actingAsEmployee()
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours('2026-10-01', '2026-10-02'),
                'days_worked' => '2',
                'late_hours' => '1',
                'overtime_hours' => '0',
            ])
            ->assertRedirect();

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                ...$this->hours('2026-10-01', '2026-10-02'),
                'days_worked' => '2',
                'late_hours' => '1',
                'overtime_hours' => '0',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.pay_basis', 'day')
                ->where('payroll.monthly_work_days', null)
                ->where('payroll.hourly_rate', '62.50')
                ->where('payroll.earnings.0.amount', '1000.00')
                ->where('payroll.earnings.0.unit', '2.00')
                ->where('payroll.earnings.1.name', 'Overtime')
                ->where('payroll.earnings.1.amount', '0.00')
                ->where('payroll.earnings.2.name', 'Commissions')
                ->where('payroll.earnings.2.amount', '0.00')
                ->where('payroll.deductions.0.amount', '62.50')
                ->where('payroll.net', '937.50'));
    }

    public function test_payroll_file_can_be_downloaded(): void
    {
        $employee = $this->employeeWithProfile();
        $employee->update([
            'name' => "Mina <script>alert('xss')</script>",
        ]);
        $employee->profile->update([
            'job_title' => 'Groomer',
            'employment_status' => EmploymentStatus::Regular,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '20000',
            'sort' => 0,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'COLA',
            'amount' => '1300',
            'sort' => 1,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Benefit,
            'name' => 'Rice allowance',
            'amount' => '260',
            'sort' => 2,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Government,
            'name' => 'SSS',
            'amount' => null,
            'employee_contribution' => '200',
            'employer_contribution' => '400',
            'sort' => 3,
        ]);

        $response = $this->actingAsEmployee()->get(
            "/backoffice/staff/{$employee->id}/payroll/download?".http_build_query($this->hours()),
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'payroll-'.$employee->employee_number.'.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $payroll = app(GeneratePayroll::class)->handle(
            $employee,
            $employee->profile->load('payLines'),
            PayrollRules::prepare($this->hours()),
        );
        $html = view('staff.payroll', [
            'employeeName' => $employee->name,
            'jobTitle' => $employee->profile->job_title,
            'employmentStatus' => $employee->profile->employment_status?->label(),
            'dateHired' => $employee->start_date->toDateString(),
            'payroll' => $payroll,
        ])->render();

        $left = strpos($html, 'class="side"');
        $right = strpos($html, 'class="side-right"');
        $this->assertNotFalse($left);
        $this->assertNotFalse($right);
        $this->assertLessThan($right, $left);
        $this->assertLessThan($right, strpos($html, '>Name<'));
        $this->assertLessThan($right, strpos($html, '>No. of days worked<'));
        $this->assertGreaterThan($right, strpos($html, '>Pay period start<'));
        $this->assertGreaterThan($right, strpos($html, '>Date hired<'));
        $this->assertStringContainsString('<h1>PAYROLL</h1>', $html);
        $this->assertStringNotContainsString((string) config('app.name'), $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Position', $html);
        $this->assertStringContainsString('Groomer', $html);
        $this->assertStringContainsString('No. of days worked', $html);
        $this->assertStringContainsString('Pay period start', $html);
        $this->assertStringContainsString('Oct 1, 2026', $html);
        $this->assertStringContainsString('Pay period end', $html);
        $this->assertStringContainsString('Oct 15, 2026', $html);
        $this->assertStringContainsString('Employee status', $html);
        $this->assertStringContainsString('Regular', $html);
        $this->assertStringContainsString('Date hired', $html);
        $this->assertStringContainsString('Jun 1, 2024', $html);
        $this->assertStringContainsString('Amount', $html);
        $this->assertStringContainsString('Unit', $html);
        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('Regular', $html);
        $this->assertStringContainsString('769.23', $html);
        $this->assertStringContainsString('10.00', $html);
        $this->assertStringContainsString('7,692.31', $html);
        $this->assertStringContainsString('Earnings', $html);
        $this->assertStringContainsString('Deductions', $html);
        $this->assertStringContainsString('Summary', $html);
        $this->assertStringContainsString('Net pay', $html);
        $this->assertStringContainsString('₱8,399.51', $html);
        $this->assertStringContainsString('SSS employer share', $html);
        $this->assertStringNotContainsString('Ordinary days are paid from the days worked', $html);
        $this->assertStringNotContainsString('Unrefunded sales in this period', $html);
        $this->assertStringNotContainsString('Employee contribution', $html);
        $this->assertStringNotContainsString('Not deducted from pay', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    public function test_government_contributions_are_omitted_when_not_deducted(): void
    {
        $employee = $this->employeeWithProfile();
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '20000',
            'sort' => 0,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'COLA',
            'amount' => '1300',
            'sort' => 1,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Benefit,
            'name' => 'Rice allowance',
            'amount' => '260',
            'sort' => 2,
        ]);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Government,
            'name' => 'SSS',
            'amount' => null,
            'employee_contribution' => '200',
            'employer_contribution' => '400',
            'sort' => 3,
        ]);

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                ...$this->hours(),
                'deduct_government' => 0,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.deduct_government', false)
                ->where('payroll.deductions', fn ($deductions) => collect($deductions)->pluck('name')->all() === ['Late'])
                ->where('payroll.employer_shares.0.amount', '400.00')
                ->where('payroll.gross', '8804.31')
                ->where('payroll.total_deductions', '204.80')
                ->where('payroll.net', '8599.51'));
    }

    public function test_regular_and_special_holiday_pay_follows_philippine_rates(): void
    {
        $employee = $this->employeeWithProfile(PayBasis::Day);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '500',
            'sort' => 0,
        ]);
        $regular = Holiday::factory()->create([
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
            'type' => HolidayType::Regular,
        ]);
        $special = Holiday::factory()->create([
            'date' => '2026-11-01',
            'name' => "All Saints' Day",
            'type' => HolidayType::Special,
        ]);

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                'period_start' => '2026-12-25',
                'period_end' => '2026-12-25',
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.1.group', 'Basic salary')
                ->where('payroll.earnings.1.label', 'Holiday')
                ->where('payroll.earnings.1.rate', '500.00')
                ->where('payroll.earnings.1.unit', '1.00')
                ->where('payroll.earnings.1.amount', '500.00')
                ->where('payroll.earnings', fn ($earnings) => collect($earnings)->pluck('name')->doesntContain('Christmas Day'))
                ->where('payroll.net', '500.00'));

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                'period_start' => '2026-12-25',
                'period_end' => '2026-12-25',
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
                'worked_holiday_ids' => [$regular->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.1.label', 'Holiday')
                ->where('payroll.earnings.1.unit', '2.00')
                ->where('payroll.earnings.1.amount', '1000.00')
                ->where('payroll.net', '1000.00'));

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-01',
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.1.label', 'Holiday')
                ->where('payroll.earnings.1.unit', '0.00')
                ->where('payroll.earnings.1.amount', '0.00')
                ->where('payroll.net', '0.00'));

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-01',
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
                'worked_holiday_ids' => [$special->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.1.label', 'Holiday')
                ->where('payroll.earnings.1.unit', '1.30')
                ->where('payroll.earnings.1.amount', '650.00')
                ->where('payroll.net', '650.00'));

        $extra = Holiday::factory()->create([
            'date' => '2026-12-26',
            'name' => 'Day after Christmas',
            'type' => HolidayType::Special,
        ]);

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                'period_start' => '2026-12-25',
                'period_end' => '2026-12-26',
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
                'worked_holiday_ids' => [$regular->id, $extra->id],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.1.label', 'Holiday')
                ->where('payroll.earnings.1.unit', '3.30')
                ->where('payroll.earnings.1.amount', '1650.00')
                ->where('payroll.earnings', fn ($earnings) => collect($earnings)->where('label', 'Holiday')->count() === 1)
                ->where('payroll.net', '1650.00'));
    }

    public function test_commissions_include_unrefunded_sales_inside_the_manila_period(): void
    {
        $employee = $this->employeeWithProfile(PayBasis::Day);
        $bgc = Branch::factory()->create(['name' => 'BGC']);
        $makati = Branch::factory()->create(['name' => 'Makati']);

        $this->recordCommission($employee, '2026-10-05 02:00:00', '150.00', branch: $makati);
        $this->recordCommission($employee, '2026-10-06 02:00:00', '10.00', branch: $makati);
        $this->recordCommission($employee, '2026-10-05 03:00:00', '25.00', branch: $bgc);
        $this->recordCommission($employee, '2026-09-30 02:00:00', '80.00', branch: $makati);
        $this->recordCommission($employee, '2026-10-05 02:00:00', '40.00', refunded: true, branch: $bgc);

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                ...$this->hours(),
                'days_worked' => '0',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.earnings.0.name', 'Overtime')
                ->where('payroll.earnings.1.group', 'Commissions')
                ->where('payroll.earnings.1.label', 'BGC')
                ->where('payroll.earnings.1.amount', '25.00')
                ->where('payroll.earnings.2.group', 'Commissions')
                ->where('payroll.earnings.2.label', 'Makati')
                ->where('payroll.earnings.2.amount', '160.00')
                ->where('payroll.net', '185.00'));
    }

    public function test_days_worked_cannot_exceed_the_period_and_holidays_must_fall_inside_it(): void
    {
        $employee = $this->employeeWithProfile();
        $holiday = Holiday::factory()->create([
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
            'type' => HolidayType::Regular,
        ]);

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours('2026-10-01', '2026-10-02'),
                'days_worked' => '3',
            ])
            ->assertSessionHasErrors([
                'days_worked' => 'Days worked cannot be more than the days in this payroll period.',
            ]);

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours(),
                'worked_holiday_ids' => [$holiday->id],
            ])
            ->assertSessionHasErrors([
                'worked_holiday_ids' => 'Choose a holiday inside this payroll period.',
            ]);

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", $this->hours('2026-10-01', '2026-11-15'))
            ->assertSessionHasErrors([
                'period_end' => 'A payroll period cannot be longer than 31 days.',
            ]);
    }

    public function test_finalized_payroll_keeps_its_snapshot(): void
    {
        $employee = $this->employeeWithProfile(PayBasis::Day);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '500',
            'sort' => 0,
        ]);

        $this->actingAsEmployee()
            ->post("/backoffice/staff/{$employee->id}/payroll/finalize", [
                ...$this->hours('2026-10-01', '2026-10-02'),
                'days_worked' => '2',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Payroll finalized.');

        $saved = EmployeePayroll::query()->whereBelongsTo($employee)->firstOrFail();
        $this->assertSame('1000.00', $saved->net);
        $this->assertSame('1000.00', $saved->snapshot['payroll']['net']);
        $this->assertNull($saved->snapshot['employment_status']);
        $this->assertSame($employee->start_date->toDateString(), $saved->snapshot['date_hired']);

        $employee->profile->payLines()->update(['amount' => '900']);

        $download = $this->actingAsEmployee()->get("/backoffice/staff/payrolls/{$saved->id}/download");
        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'payroll-'.$employee->employee_number.'-2026-10-01.pdf',
            (string) $download->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $download->getContent());
        $this->assertSame('1000.00', $saved->refresh()->snapshot['payroll']['net']);
    }

    public function test_overnight_allowance_and_one_time_items_change_the_payroll(): void
    {
        $employee = $this->employeeWithProfile(PayBasis::Day);
        $employee->profile->update(['overnight_allowance' => '150.00']);
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '500',
            'sort' => 0,
        ]);

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours('2026-10-01', '2026-10-05'),
                'days_worked' => '2',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
                'overnight_nights' => '3',
                'adjustments' => [
                    ['name' => '', 'side' => 'earning', 'amount' => ''],
                    ['name' => 'Bonus', 'side' => 'earning', 'amount' => '200'],
                    ['name' => 'Cash advance', 'side' => 'deduction', 'amount' => '50'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAsEmployee()
            ->get(route('staff.record.show', [
                'employee' => $employee,
                ...$this->hours('2026-10-01', '2026-10-05'),
                'days_worked' => '2',
                'late_hours' => '0',
                'overtime_hours' => '0',
                'deduct_government' => 0,
                'overnight_nights' => '3',
                'adjustments' => [
                    ['name' => 'Bonus', 'side' => 'earning', 'amount' => '200'],
                    ['name' => 'Cash advance', 'side' => 'deduction', 'amount' => '50'],
                ],
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payroll.hourly_rate', '62.50')
                ->where('payroll.earnings.1.name', 'Overnight allowance')
                ->where('payroll.earnings.1.rate', '150.00')
                ->where('payroll.earnings.1.unit', '3.00')
                ->where('payroll.earnings.1.amount', '450.00')
                ->where('payroll.earnings.2.name', 'Overtime')
                ->where('payroll.earnings.3.name', 'Commissions')
                ->where('payroll.earnings.4.name', 'Bonus')
                ->where('payroll.earnings.4.rate', '200.00')
                ->where('payroll.earnings.4.unit', '1.00')
                ->where('payroll.earnings.4.amount', '200.00')
                ->where('payroll.deductions.1.name', 'Cash advance')
                ->where('payroll.deductions.1.amount', '50.00')
                ->where('payroll.net', '1600.00'));

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours('2026-10-01', '2026-10-02'),
                'days_worked' => '2',
                'overnight_nights' => '3',
            ])
            ->assertSessionHasErrors([
                'overnight_nights' => 'Overnight nights cannot be more than the days in this payroll period.',
            ]);

        $this->actingAsEmployee()
            ->from("/backoffice/staff/{$employee->id}/record")
            ->post("/backoffice/staff/{$employee->id}/payroll", [
                ...$this->hours('2026-10-01', '2026-10-02'),
                'adjustments' => [
                    ['name' => 'Bonus', 'side' => 'earning', 'amount' => ''],
                ],
            ])
            ->assertSessionHasErrors([
                'adjustments.0.amount' => 'Enter an amount for the one-time item.',
            ]);
    }

    private function recordCommission(Employee $employee, string $soldAt, string $amount, bool $refunded = false, ?Branch $branch = null): void
    {
        $sale = PosSale::factory()->create([
            'sold_at' => $soldAt,
            'refunded_at' => $refunded ? $soldAt : null,
            ...($branch === null ? [] : ['branch_id' => $branch->id]),
        ]);
        $item = PosSaleItem::factory()->create([
            'pos_sale_id' => $sale->id,
        ]);

        PosSaleItemCommission::factory()->create([
            'pos_sale_item_id' => $item->id,
            'employee_id' => $employee->id,
            'amount' => $amount,
        ]);
    }

    private function employeeWithProfile(PayBasis $basis = PayBasis::Month): Employee
    {
        $employee = Employee::factory()->create();
        EmployeeProfile::factory()->create([
            'employee_id' => $employee->id,
            'pay_basis' => $basis,
        ]);

        return $employee->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function hours(string $start = '2026-10-01', string $end = '2026-10-15'): array
    {
        return [
            'period_start' => $start,
            'period_end' => $end,
            'days_worked' => '10',
            'late_hours' => '2',
            'overtime_hours' => '4',
            'overnight_nights' => '0',
            'deduct_government' => 1,
            'worked_holiday_ids' => [],
            'adjustments' => [],
        ];
    }
}
