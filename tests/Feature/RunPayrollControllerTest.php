<?php

namespace Tests\Feature;

use App\Enums\HolidayType;
use App\Enums\PayBasis;
use App\Enums\PayLineKind;
use App\Enums\PayrollFilingStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\PayrollFiling;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RunPayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_payroll_run(): void
    {
        $this->get(route('staff.payroll.run'))->assertRedirect(route('login'));
        $this->post(route('staff.payroll.prepare'))->assertRedirect(route('login'));
        $this->post(route('staff.payroll.preview'))->assertRedirect(route('login'));
        $this->post(route('staff.payroll.finalize'))->assertRedirect(route('login'));
        $this->get(route('staff.payroll.download-all'))->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_cannot_run_payroll(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)->get(route('staff.payroll.run'))->assertForbidden();
        $this->actingAsEmployee($employee)->post(route('staff.payroll.prepare'))->assertForbidden();
        $this->actingAsEmployee($employee)->post(route('staff.payroll.preview'))->assertForbidden();
        $this->actingAsEmployee($employee)->post(route('staff.payroll.finalize'))->assertForbidden();
        $this->actingAsEmployee($employee)->get(route('staff.payroll.download-all'))->assertForbidden();
    }

    public function test_run_page_lists_active_employees_with_a_pay_record(): void
    {
        $listed = Employee::factory()->create(['name' => 'Mina Cruz']);
        EmployeeProfile::factory()->create(['employee_id' => $listed->id]);
        $inactive = Employee::factory()->inactive()->create(['name' => 'Former Staff']);
        EmployeeProfile::factory()->create(['employee_id' => $inactive->id]);
        Employee::factory()->create(['name' => 'No Record']);

        $this->actingAsEmployee()
            ->get(route('staff.payroll.run'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Payroll/Run')
                ->where('employees', function (mixed $employees) use ($listed): bool {
                    $employees = collect($employees);

                    return $employees->count() === 1
                        && $employees[0]['name'] === 'Mina Cruz'
                        && $employees[0]['id'] === $listed->id;
                })
                ->where('prepared', null)
                ->where('preview', null)
                ->where('completed', null));
    }

    public function test_prepare_fills_ordinary_days_overnights_commissions_and_cash_advances(): void
    {
        $mina = $this->employeeWithPay('Mina Cruz');
        $leo = $this->employeeWithPay('Leo Santos');
        Holiday::factory()->create([
            'date' => '2026-11-02',
            'name' => 'All Souls Day',
            'type' => HolidayType::Regular,
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-04',
            'nights' => 2,
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => '2026-11-05',
            'nights' => 3,
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-10-20',
            'nights' => 1,
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $leo->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-04',
            'nights' => 4,
        ]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-03',
            'amount' => '200.00',
            'reason' => 'Medicine',
        ]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => '2026-11-03',
            'amount' => '999.00',
        ]);
        $alreadyPaid = EmployeePayroll::factory()->create(['employee_id' => $mina->id]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-01',
            'amount' => '50.00',
            'deducted_payroll_id' => $alreadyPaid->id,
        ]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $leo->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-03',
            'amount' => '80.00',
            'reason' => 'Fare',
        ]);
        $branch = Branch::factory()->create(['name' => 'BGC']);
        $this->recordCommission($mina, '2026-11-04 02:00:00', '25.00', $branch);
        $this->recordCommission($mina, '2026-10-30 02:00:00', '80.00', $branch);

        $this->actingAsEmployee()
            ->followingRedirects()
            ->post(route('staff.payroll.prepare'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-07',
                'deduct_government' => false,
                'employee_ids' => [$mina->id, $leo->id],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('prepared.period_label', 'Nov 1, 2026 – Nov 7, 2026')
                ->where('prepared.deduct_government', false)
                ->where('prepared.calendar', function (mixed $days): bool {
                    $days = collect($days);

                    return $days->count() === 7
                        && $days->firstWhere('date', '2026-11-01')['is_day_off'] === true
                        && $days->firstWhere('date', '2026-11-01')['holiday'] === null
                        && $days->firstWhere('date', '2026-11-02')['holiday']['name'] === 'All Souls Day'
                        && $days->firstWhere('date', '2026-11-02')['holiday']['type_label'] === 'Regular';
                })
                ->where('prepared.employees', function (mixed $employees) use ($mina, $leo): bool {
                    $employees = collect($employees)->keyBy('id');
                    $first = $employees[$mina->id];
                    $second = $employees[$leo->id];

                    return $first['days_worked'] === 5
                        && $first['worked_dates'] === ['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07']
                        && $first['overnight_nights'] === 2
                        && $first['commissions'][0]['name'] === 'Commissions — BGC'
                        && $first['commissions'][0]['amount'] === '25.00'
                        && count($first['cash_advances']) === 1
                        && $first['cash_advances'][0]['amount'] === '200.00'
                        && $first['cash_advances'][0]['reason'] === 'Medicine'
                        && $second['days_worked'] === 5
                        && $second['overnight_nights'] === 4
                        && $second['commissions'][0]['amount'] === '0.00'
                        && count($second['cash_advances']) === 1
                        && $second['cash_advances'][0]['amount'] === '80.00';
                }));
    }

    public function test_prepare_puts_filed_leaves_and_overnights_on_the_calendar(): void
    {
        $mina = $this->employeeWithPay('Mina Cruz');
        $leo = $this->employeeWithPay('Leo Santos');
        $other = $this->employeeWithPay('Ada Reyes');
        PayrollFiling::factory()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-10-31',
            'ends_on' => '2026-11-02',
            'reason' => 'Family day',
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $leo->id,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => '2026-11-06',
            'nights' => 2,
            'reason' => 'Boarding',
        ]);
        PayrollFiling::factory()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Rejected,
            'starts_on' => '2026-11-03',
            'ends_on' => '2026-11-03',
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $other->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-04',
            'nights' => 1,
        ]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-04',
        ]);

        $this->actingAsEmployee()
            ->followingRedirects()
            ->post(route('staff.payroll.prepare'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-07',
                'employee_ids' => [$mina->id, $leo->id],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('prepared.calendar', function (mixed $days): bool {
                    $days = collect($days)->keyBy('date');
                    $leave = collect($days['2026-11-01']['filings']);
                    $overnight = collect($days['2026-11-06']['filings']);

                    return $leave->count() === 1
                        && $leave->first()['employee'] === 'Mina Cruz'
                        && $leave->first()['type'] === 'leave'
                        && $leave->first()['status'] === 'approved'
                        && $leave->first()['reason'] === 'Family day'
                        && collect($days['2026-11-02']['filings'])->contains(fn (array $filing): bool => $filing['type'] === 'leave')
                        && collect($days['2026-11-03']['filings'])->isEmpty()
                        && $overnight->count() === 1
                        && $overnight->first()['employee'] === 'Leo Santos'
                        && $overnight->first()['type'] === 'overnight'
                        && $overnight->first()['status'] === 'pending'
                        && collect($days['2026-11-07']['filings'])->contains(fn (array $filing): bool => $filing['employee'] === 'Leo Santos')
                        && collect($days['2026-11-04']['filings'])->isEmpty();
                }));
    }

    public function test_refreshing_the_prepare_address_returns_to_the_payroll_run(): void
    {
        $this->actingAsEmployee()
            ->get('/backoffice/staff/payroll/prepare')
            ->assertRedirect(route('staff.payroll.run'));
    }

    public function test_prepare_rejects_a_long_period_and_employees_without_a_pay_record(): void
    {
        $employee = $this->employeeWithPay('Mina Cruz');
        $inactive = Employee::factory()->inactive()->create();
        EmployeeProfile::factory()->create(['employee_id' => $inactive->id]);

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.prepare'), [])
            ->assertRedirect(route('staff.payroll.run'))
            ->assertSessionHasErrors([
                'period_start' => 'Enter the payroll start date.',
                'period_end' => 'Enter the payroll end date.',
                'employee_ids' => 'Choose at least one employee.',
            ]);

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.prepare'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-12-15',
                'employee_ids' => [$employee->id],
            ])
            ->assertSessionHasErrors([
                'period_end' => 'A payroll period cannot be longer than 31 days.',
            ]);

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.prepare'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-07',
                'employee_ids' => [$inactive->id],
            ])
            ->assertSessionHasErrors([
                'employee_ids' => 'Choose active employees with a pay record.',
            ]);
    }

    public function test_preview_shows_each_payslip_without_saving_it(): void
    {
        $mina = $this->employeeWithPay('Mina Cruz');
        $branch = Branch::factory()->create(['name' => 'BGC']);
        $this->recordCommission($mina, '2026-11-04 02:00:00', '25.00', $branch);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-03',
            'amount' => '200.00',
            'reason' => 'Medicine',
        ]);

        $response = $this->actingAsEmployee()
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-03',
                'period_end' => '2026-11-04',
                'employees' => [$this->attendance($mina)],
            ]);

        $this->assertSame(0, EmployeePayroll::query()->count());

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.employees.0.net', '825.00')
                ->where('preview.total', '825.00')
                ->where('preview.employees.0.preview_html', fn (string $html): bool => str_contains($html, 'PAYROLL') && str_contains($html, 'Mina Cruz')));
    }

    public function test_preview_deducts_government_benefits_only_when_the_run_says_yes(): void
    {
        $employee = $this->employeeWithPay('Mina Cruz');
        $employee->profile->payLines()->create([
            'kind' => PayLineKind::Government,
            'name' => 'SSS',
            'amount' => null,
            'employee_contribution' => '150.00',
            'employer_contribution' => '300.00',
            'sort' => 1,
        ]);
        $attendance = $this->attendance($employee);

        $this->actingAsEmployee()
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-03',
                'period_end' => '2026-11-04',
                'deduct_government' => false,
                'employees' => [$attendance],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.deduct_government', false)
                ->where('preview.employees.0.net', '1000.00'));

        $this->actingAsEmployee()
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-03',
                'period_end' => '2026-11-04',
                'deduct_government' => true,
                'employees' => [$attendance],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.deduct_government', true)
                ->where('preview.employees.0.net', '850.00'));

        $this->assertSame(0, EmployeePayroll::query()->count());
    }

    public function test_preview_allows_every_employee_to_mark_the_same_holidays(): void
    {
        $mina = $this->employeeWithPay('Mina Cruz');
        $leo = $this->employeeWithPay('Leo Santos');
        $allSaints = Holiday::factory()->create([
            'date' => '2026-11-01',
            'name' => "All Saints' Day",
            'type' => HolidayType::Special,
        ]);
        $allSouls = Holiday::factory()->create([
            'date' => '2026-11-02',
            'name' => "All Souls' Day",
            'type' => HolidayType::Special,
        ]);
        $minaAttendance = $this->attendance($mina);
        $minaAttendance['worked_holiday_ids'] = [$allSaints->id, $allSouls->id];
        $leoAttendance = $this->attendance($leo);
        $leoAttendance['worked_holiday_ids'] = [$allSaints->id, $allSouls->id];

        $this->actingAsEmployee()
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-02',
                'deduct_government' => false,
                'employees' => [$minaAttendance, $leoAttendance],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.employees.0.net', '2300.00')
                ->where('preview.employees.1.net', '2300.00')
                ->where('preview.employees.0.preview_html', fn (mixed $html): bool => is_string($html)
                    && preg_match('/No\. of days worked<\/th>\s*<td>4<\/td>/', $html) === 1
                    && str_contains($html, '>2.00<')));

        $duplicate = $this->attendance($mina);
        $duplicate['worked_holiday_ids'] = [$allSaints->id, $allSaints->id];

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-02',
                'employees' => [$duplicate],
            ])
            ->assertRedirect(route('staff.payroll.run'))
            ->assertSessionHasErrors([
                'employees.0.worked_holiday_ids' => 'Each holiday can only be marked once.',
            ]);
    }

    public function test_preview_counts_ordinary_days_and_holidays_from_the_selected_dates(): void
    {
        $employee = $this->employeeWithPay('Mina Cruz');
        $allSaints = Holiday::factory()->create([
            'date' => '2026-11-01',
            'name' => "All Saints' Day",
            'type' => HolidayType::Special,
        ]);
        $allSouls = Holiday::factory()->create([
            'date' => '2026-11-02',
            'name' => "All Souls' Day",
            'type' => HolidayType::Special,
        ]);
        $attendance = $this->attendance($employee, days: '9');
        $attendance['worked_holiday_ids'] = [$allSaints->id, $allSouls->id];
        $attendance['worked_dates'] = ['2026-11-01', '2026-11-03'];

        $this->actingAsEmployee()
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-03',
                'deduct_government' => false,
                'employees' => [$attendance],
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.employees.0.net', '1150.00')
                ->where('preview.draft.0.days_worked', '1')
                ->where('preview.draft.0.worked_holiday_ids', [$allSaints->id])
                ->where('preview.draft.0.worked_dates', ['2026-11-01', '2026-11-03'])
                ->where('preview.employees.0.preview_html', fn (mixed $html): bool => is_string($html)
                    && preg_match('/No\. of days worked<\/th>\s*<td>2<\/td>/', $html) === 1));
    }

    public function test_preview_rejects_a_duplicate_worked_day(): void
    {
        $employee = $this->employeeWithPay('Mina Cruz');
        $attendance = $this->attendance($employee);
        $attendance['worked_dates'] = ['2026-11-03', '2026-11-03'];

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-03',
                'period_end' => '2026-11-04',
                'employees' => [$attendance],
            ])
            ->assertRedirect(route('staff.payroll.run'))
            ->assertSessionHasErrors([
                'employees.0.worked_dates' => 'Choose each day once.',
            ]);
    }

    public function test_preview_rejects_days_worked_beyond_the_period(): void
    {
        $employee = $this->employeeWithPay('Mina Cruz');

        $this->actingAsEmployee()
            ->from(route('staff.payroll.run'))
            ->post(route('staff.payroll.preview'), [
                'period_start' => '2026-11-03',
                'period_end' => '2026-11-04',
                'employees' => [$this->attendance($employee, days: '10')],
            ])
            ->assertRedirect(route('staff.payroll.run'))
            ->assertSessionHasErrors([
                'employees.0.days_worked' => 'Days worked cannot be more than the days in this payroll period.',
            ]);

        $this->assertSame(0, EmployeePayroll::query()->count());
    }

    public function test_finalize_saves_every_payslip_and_a_later_run_does_not_deduct_the_same_cash_advance(): void
    {
        $mina = $this->employeeWithPay('Mina Cruz');
        $leo = $this->employeeWithPay('Leo Santos');
        $branch = Branch::factory()->create(['name' => 'BGC']);
        $this->recordCommission($mina, '2026-11-04 02:00:00', '25.00', $branch);
        $advance = PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $mina->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-11-03',
            'amount' => '200.00',
            'reason' => 'Medicine',
        ]);
        $payload = [
            'period_start' => '2026-11-03',
            'period_end' => '2026-11-04',
            'employees' => [
                $this->attendance($mina),
                $this->attendance($leo),
            ],
        ];

        $this->actingAsEmployee()
            ->post(route('staff.payroll.finalize'), $payload)
            ->assertRedirect(route('staff.payroll.run'))
            ->assertSessionHas('status', 'Payroll finalized.');

        $minaPayroll = EmployeePayroll::query()->whereBelongsTo($mina)->firstOrFail();
        $leoPayroll = EmployeePayroll::query()->whereBelongsTo($leo)->firstOrFail();
        $this->assertSame('825.00', $minaPayroll->net);
        $this->assertSame('1000.00', $leoPayroll->net);
        $this->assertTrue($minaPayroll->snapshot['payroll']['deduct_government']);
        $this->assertSame($minaPayroll->id, $advance->refresh()->deducted_payroll_id);

        $this->get(route('staff.payroll.run'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('completed.total', '1825.00')
                ->where('completed.employees.0.net', '825.00')
                ->where('completed.employees.1.net', '1000.00'));

        $this->actingAsEmployee($mina)
            ->get(route('me.payroll'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payrolls.0.net', '₱825.00')
                ->where('payrolls.0.period', 'Nov 3, 2026 – Nov 4, 2026'));

        $download = $this->actingAsEmployee()->get(route('staff.payroll.download-all', [
            'ids' => [$minaPayroll->id, $leoPayroll->id],
        ]));
        $download->assertOk();
        $this->assertStringContainsString('.zip', (string) $download->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $download->getContent());

        $this->actingAsEmployee()
            ->post(route('staff.payroll.finalize'), $payload)
            ->assertRedirect(route('staff.payroll.run'));

        $latest = EmployeePayroll::query()->whereBelongsTo($mina)->latest('id')->firstOrFail();
        $this->assertSame('1025.00', $latest->net);
        $this->assertSame($minaPayroll->id, $advance->refresh()->deducted_payroll_id);
        $this->assertSame(2, EmployeePayroll::query()->whereBelongsTo($leo)->count());
    }

    private function employeeWithPay(string $name): Employee
    {
        $employee = Employee::factory()->create(['name' => $name]);
        $profile = EmployeeProfile::factory()->create([
            'employee_id' => $employee->id,
            'pay_basis' => PayBasis::Day,
        ]);
        $profile->payLines()->create([
            'kind' => PayLineKind::Compensation,
            'name' => 'Basic salary',
            'amount' => '500',
            'sort' => 0,
        ]);

        return $employee->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function attendance(Employee $employee, string $days = '2'): array
    {
        return [
            'id' => $employee->id,
            'days_worked' => $days,
            'late_hours' => '0',
            'overtime_hours' => '0',
            'overnight_nights' => '0',
            'worked_holiday_ids' => [],
            'adjustments' => [],
        ];
    }

    private function recordCommission(Employee $employee, string $soldAt, string $amount, Branch $branch): void
    {
        $sale = PosSale::factory()->create([
            'sold_at' => $soldAt,
            'refunded_at' => null,
            'branch_id' => $branch->id,
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
}
