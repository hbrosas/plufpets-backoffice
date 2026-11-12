<?php

namespace Tests\Feature;

use App\Enums\HolidayType;
use App\Enums\PayrollFilingStatus;
use App\Enums\PosCashMovementType;
use App\Enums\ShopOrderStatus;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemVariant;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\HotelStayPayment;
use App\Models\HotelStayPet;
use App\Models\PayrollFiling;
use App\Models\PosCashMovement;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItemCommission;
use App\Models\PosSalePayment;
use App\Models\PosShift;
use App\Models\Role;
use App\Models\SmsCreditBalance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/backoffice/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_employee_sees_the_dashboard(): void
    {
        $response = $this->withSession([
            'employee' => [
                'employee_number' => '10001',
                'name' => 'Sample Employee',
            ],
        ])->get('/backoffice/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('employee.employee_number', '10001')
            ->where('employee.name', 'Sample Employee')
            ->where('employee.has_record', false)
            ->where('commissions.scope', 'all')
            ->where('pulse.metrics.0.label', 'Revenue')
            ->where('pulse.metrics.0.today', '₱0.00')
            ->where('stock.low', [])
            ->missing('activeStaffCount')
            ->where('smsCreditBalance', null));
    }

    public function test_sms_credit_balance_is_shared_for_an_employee_who_can_view_it(): void
    {
        SmsCreditBalance::factory()->create(['balance' => 1250]);
        $viewer = $this->employeeWith(['dashboard.view', 'sms.balance']);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('smsCreditBalance.balance', 1250)
                ->where('smsCreditBalance.label', '1,250'));
    }

    public function test_sms_credit_balance_is_hidden_without_the_permission(): void
    {
        SmsCreditBalance::factory()->create(['balance' => 1250]);
        $viewer = $this->employeeWith(['dashboard.view']);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('smsCreditBalance', null));
    }

    public function test_viewer_without_sales_access_does_not_receive_sales_or_other_commissions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, 25);
        $this->commission($other, 40);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('pulse', null)
                ->where('stock', null)
                ->missing('activeStaffCount')
                ->where('commissions.scope', 'self')
                ->where('commissions.report.total', '₱25.00')
                ->has('commissions.report.employees', 1)
                ->where('commissions.report.employees.0.name', 'Ada Groomer')
                ->where('attendance.leaves', 0)
                ->where('attendance.overnights', 0)
                ->where('branchOrders', [])
                ->where('schedule', null));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard?preset=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('commissions.filters.preset', 'week')
                ->where('commissions.report.total', '₱25.00'));
    }

    public function test_employee_dashboard_counts_approved_leave_days_and_overnight_nights(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create();

        PayrollFiling::factory()->create([
            'employee_id' => $viewer->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-06-14',
            'ends_on' => '2026-06-16',
        ]);
        PayrollFiling::factory()->create([
            'employee_id' => $viewer->id,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => '2026-06-12',
            'ends_on' => '2026-06-12',
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $viewer->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-06-10',
            'nights' => 2,
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $other->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-06-10',
            'nights' => 4,
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.leaves', 2)
                ->where('attendance.overnights', 2)
                ->where('branchOrders', []));

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard?preset=week')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.leaves', 1)
                ->where('attendance.overnights', 0));
    }

    public function test_sales_viewer_receives_branch_sales_and_every_commission(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $viewer = $this->employeeWith(['dashboard.view', 'sales.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create([
            'name' => 'Ben Cashier',
            'employee_number' => '30002',
        ]);

        $this->commission($viewer, 25);
        $this->commission($other, 40);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse.metrics.0.label', 'Revenue')
                ->where('commissions.scope', 'all')
                ->where('commissions.report.total', '₱65.00')
                ->has('commissions.report.employees', 2)
                ->where('commissions.report.employees.0.name', 'Ben Cashier')
                ->where('stock', null)
                ->missing('activeStaffCount')
                ->where('attendance', null)
                ->where('cashAdvances', null)
                ->where('schedule', null));
    }

    public function test_staff_manager_sees_employee_requests_and_the_filing_calendar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $manager = $this->employeeWith(['dashboard.view', 'staff.manage'], 'Ada Manager', '30001');
        $ada = Employee::factory()->create(['name' => 'Ada Groomer']);
        $ben = Employee::factory()->create(['name' => 'Ben Cashier']);

        PayrollFiling::factory()->overtime()->create([
            'employee_id' => $ben->id,
            'starts_on' => '2026-06-15',
        ]);
        PayrollFiling::factory()->create([
            'employee_id' => $ada->id,
            'starts_on' => '2026-06-16',
            'ends_on' => '2026-06-18',
            'reason' => 'Family day',
        ]);
        PayrollFiling::factory()->overnight()->create([
            'employee_id' => $ben->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-06-14',
            'nights' => 2,
        ]);
        PayrollFiling::factory()->create([
            'employee_id' => $ada->id,
            'status' => PayrollFilingStatus::Rejected,
            'starts_on' => '2026-06-20',
            'ends_on' => '2026-06-20',
        ]);
        PayrollFiling::factory()->create([
            'employee_id' => $ada->id,
            'status' => PayrollFilingStatus::Approved,
            'starts_on' => '2026-07-02',
            'ends_on' => '2026-07-02',
        ]);
        Holiday::factory()->create([
            'date' => '2026-06-12',
            'name' => 'Independence Day',
            'type' => HolidayType::Regular,
        ]);
        Holiday::factory()->special()->create([
            'date' => '2026-07-04',
            'name' => 'City festival',
        ]);

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedule.month', '2026-06')
                ->where('schedule.label', 'June 2026')
                ->where('schedule.previous', '2026-05')
                ->where('schedule.next', '2026-07')
                ->has('schedule.requests', 2)
                ->where('schedule.requests.0.type', 'overtime')
                ->where('schedule.requests.0.employee', 'Ben Cashier')
                ->where('schedule.requests.1.type', 'leave')
                ->where('schedule.requests.1.employee', 'Ada Groomer')
                ->where('schedule.requests.1.reason', 'Family day')
                ->has('schedule.events', 2)
                ->where('schedule.events.0.type', 'overnight')
                ->where('schedule.events.0.employee', 'Ben Cashier')
                ->where('schedule.events.0.status', 'approved')
                ->where('schedule.events.0.dates', ['2026-06-14', '2026-06-15'])
                ->where('schedule.events.1.type', 'leave')
                ->where('schedule.events.1.status', 'pending')
                ->where('schedule.events.1.dates', ['2026-06-16', '2026-06-17', '2026-06-18'])
                ->has('schedule.holidays', 1)
                ->where('schedule.holidays.0.name', 'Independence Day')
                ->where('schedule.holidays.0.type', 'regular')
                ->where('schedule.holidays.0.date', '2026-06-12'));

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard?month=2026-07')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedule.month', '2026-07')
                ->has('schedule.requests', 2)
                ->has('schedule.events', 1)
                ->where('schedule.events.0.dates', ['2026-07-02'])
                ->has('schedule.holidays', 1)
                ->where('schedule.holidays.0.name', 'City festival')
                ->where('schedule.holidays.0.date', '2026-07-04'));

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard?month=nope')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('schedule.month', '2026-06'));
    }

    public function test_staff_manager_sees_every_commission(): void
    {
        $manager = $this->employeeWith(['dashboard.view', 'staff.manage'], 'Ada Groomer', '30001');

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse', null)
                ->where('commissions.scope', 'all')
                ->missing('activeStaffCount'));
    }

    public function test_inventory_viewer_receives_low_stock_without_sales_figures(): void
    {
        $viewer = $this->employeeWith(['dashboard.view', 'inventory.view']);
        $variant = CatalogItemVariant::factory()->create([
            'catalog_item_id' => CatalogItem::factory()->create(['name' => 'Bowl'])->id,
            'name' => 'Default',
        ]);
        BranchInventory::factory()->create([
            'catalog_item_variant_id' => $variant->id,
            'quantity' => 0,
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pulse', null)
                ->where('stock.low.0.product', 'Bowl')
                ->where('stock.low.0.status', 'Out of stock')
                ->where('stock.low.0.quantity', 0));
    }

    public function test_dashboard_lists_open_and_online_orders_for_each_branch(): void
    {
        $greenheights = Branch::factory()->create(['name' => 'Greenheights', 'is_active' => true]);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro', 'is_active' => true]);
        Branch::factory()->create(['name' => 'Closed', 'is_active' => false]);
        $viewer = $this->employeeWith(['dashboard.view', 'sales.view', 'shop.orders', 'pos.access']);

        PosOpenOrder::factory()->create([
            'branch_id' => $greenheights->id,
            'name' => 'Walk-in hold',
            'status' => null,
            'subtotal' => 80,
        ]);
        $online = PosOpenOrder::factory()->create([
            'branch_id' => $greenheights->id,
            'name' => 'Maya Cruz',
            'saved_by_name' => 'Maya Cruz',
            'status' => ShopOrderStatus::Preparing->value,
            'total' => 120,
        ]);
        PosOpenOrder::factory()->create([
            'branch_id' => $greenheights->id,
            'status' => ShopOrderStatus::Completed->value,
        ]);
        PosOpenOrder::factory()->create([
            'branch_id' => $sanPedro->id,
            'name' => 'Counter hold',
            'status' => null,
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('branchOrders', 2)
                ->where('branchOrders.0.name', 'Greenheights')
                ->where('branchOrders.0.pos_href', route('pos.index', $greenheights))
                ->where('branchOrders.0.sales_label', '₱0.00')
                ->where('branchOrders.0.cash_label', '₱0.00')
                ->where('branchOrders.0.activities', [])
                ->has('branchOrders.0.open', 1)
                ->where('branchOrders.0.open.0.name', 'Walk-in hold')
                ->where('branchOrders.0.open.0.total_label', '₱80.00')
                ->has('branchOrders.0.online', 1)
                ->where('branchOrders.0.online.0.number', $online->number)
                ->where('branchOrders.0.online.0.customer', 'Maya Cruz')
                ->where('branchOrders.0.online.0.status_label', 'Preparing Order')
                ->where('branchOrders.0.online.0.total_label', '₱120.00')
                ->where('branchOrders.0.online.0.href', route('shop.orders.show', $online))
                ->where('branchOrders.1.name', 'San Pedro')
                ->has('branchOrders.1.open', 1)
                ->where('branchOrders.1.online', []));
    }

    public function test_todays_activities_show_branch_sales_cash_and_shift_events(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $greenheights = Branch::factory()->create(['name' => 'Greenheights']);
        $sanPedro = Branch::factory()->create(['name' => 'San Pedro']);
        $viewer = $this->employeeWith(['dashboard.view', 'sales.view']);
        $openShift = PosShift::factory()->create([
            'branch_id' => $greenheights->id,
            'opened_by_name' => 'Hazel Brosas',
            'opened_at' => CarbonImmutable::parse('2026-06-14 02:00:00', 'UTC'),
            'starting_cash' => 1000,
        ]);

        PosSale::factory()->create([
            'pos_shift_id' => $openShift->id,
            'branch_id' => $greenheights->id,
            'number' => 'POS-YDAY',
            'is_cash' => true,
            'payment_method_name' => 'Cash',
            'total' => 100,
            'sold_at' => CarbonImmutable::parse('2026-06-14 04:00:00', 'UTC'),
        ]);
        $cashSale = PosSale::factory()->create([
            'pos_shift_id' => $openShift->id,
            'branch_id' => $greenheights->id,
            'number' => 'POS-CASH',
            'is_cash' => true,
            'payment_method_name' => 'Cash',
            'total' => 200,
            'sold_by_name' => 'Hazel Brosas',
            'sold_at' => CarbonImmutable::parse('2026-06-15 02:00:00', 'UTC'),
        ]);
        PosSalePayment::factory()->create([
            'pos_sale_id' => $cashSale->id,
            'payment_method_name' => 'Cash',
            'is_cash' => true,
            'amount' => 200,
        ]);
        PosSale::factory()->create([
            'pos_shift_id' => $openShift->id,
            'branch_id' => $greenheights->id,
            'number' => 'POS-CARD',
            'is_cash' => false,
            'payment_method_name' => 'Card',
            'total' => 80,
            'sold_at' => CarbonImmutable::parse('2026-06-15 03:00:00', 'UTC'),
        ]);
        PosSale::factory()->create([
            'pos_shift_id' => $openShift->id,
            'branch_id' => $greenheights->id,
            'number' => 'POS-BACK',
            'is_cash' => true,
            'payment_method_name' => 'Cash',
            'total' => 40,
            'sold_at' => CarbonImmutable::parse('2026-06-15 02:10:00', 'UTC'),
            'refunded_at' => CarbonImmutable::parse('2026-06-15 03:45:00', 'UTC'),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $openShift->id,
            'type' => PosCashMovementType::CashIn,
            'amount' => 50,
            'reason' => 'Change fund',
            'employee_name' => 'Hazel Brosas',
            'recorded_at' => CarbonImmutable::parse('2026-06-15 02:30:00', 'UTC'),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $openShift->id,
            'type' => PosCashMovementType::CashOut,
            'amount' => 20,
            'reason' => 'Supplies',
            'employee_name' => 'Hazel Brosas',
            'recorded_at' => CarbonImmutable::parse('2026-06-15 03:30:00', 'UTC'),
        ]);
        PosCashMovement::factory()->create([
            'pos_shift_id' => $openShift->id,
            'type' => PosCashMovementType::OpenDrawer,
            'amount' => 0,
            'reason' => '',
            'recorded_at' => CarbonImmutable::parse('2026-06-15 03:40:00', 'UTC'),
        ]);
        $closed = PosShift::factory()->closed()->create([
            'branch_id' => $sanPedro->id,
            'opened_by_name' => 'Robin Santiago',
            'closed_by_name' => 'Robin Santiago',
            'opened_at' => CarbonImmutable::parse('2026-06-15 00:00:00', 'UTC'),
            'closed_at' => CarbonImmutable::parse('2026-06-15 00:30:00', 'UTC'),
            'starting_cash' => 500,
            'counted_cash' => 480,
        ]);
        PosSale::factory()->create([
            'pos_shift_id' => $closed->id,
            'branch_id' => $sanPedro->id,
            'number' => 'POS-PEDRO',
            'is_cash' => true,
            'total' => 60,
            'sold_at' => CarbonImmutable::parse('2026-06-15 00:15:00', 'UTC'),
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branchOrders.0.name', 'Greenheights')
                ->where('branchOrders.0.sales_label', '₱280.00')
                ->where('branchOrders.0.cash_label', '₱1,330.00')
                ->has('branchOrders.0.activities', 7)
                ->where('branchOrders.0.activities.0.title', 'Refund')
                ->where('branchOrders.0.activities.0.amount_label', '-₱40.00')
                ->where('branchOrders.0.activities.0.time', 'Jun 15, 11:45 AM')
                ->where('branchOrders.0.activities.1.title', 'Cash out')
                ->where('branchOrders.0.activities.1.amount_label', '-₱20.00')
                ->where('branchOrders.1.name', 'San Pedro')
                ->where('branchOrders.1.sales_label', '₱60.00')
                ->where('branchOrders.1.cash_label', '₱480.00')
                ->where('branchOrders.1.activities.0.title', 'Closing cash')
                ->where('branchOrders.1.activities.0.amount_label', '₱480.00')
                ->where('branchOrders.1.activities.1.title', 'Transaction')
                ->where('branchOrders.1.activities.2.title', 'Starting cash')
                ->where('branchOrders.1.activities.2.amount_label', '₱500.00'));
    }

    public function test_employee_dashboard_lists_their_cash_advances(): void
    {
        $viewer = $this->employeeWith(['dashboard.view'], 'Ada Groomer', '30001');
        $other = Employee::factory()->create();
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $viewer->id,
            'starts_on' => '2026-11-07',
            'amount' => '500.00',
            'reason' => 'Emergency',
        ]);
        PayrollFiling::factory()->cashAdvance()->create([
            'employee_id' => $other->id,
            'amount' => '900.00',
            'reason' => 'Someone else',
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cashAdvances', 1)
                ->where('cashAdvances.0.amount', '₱500.00')
                ->where('cashAdvances.0.reason', 'Emergency')
                ->where('cashAdvances.0.status_label', 'Pending')
                ->where('cashAdvances.0.requested_on', 'Nov 7, 2026'));
    }

    public function test_shift_activity_lists_a_hotel_payment_by_the_amount_paid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $branch = Branch::factory()->create(['name' => 'Greenheights']);
        $viewer = $this->employeeWith(['dashboard.view', 'sales.view']);
        $shift = PosShift::factory()->create([
            'branch_id' => $branch->id,
            'opened_at' => CarbonImmutable::parse('2026-06-15 01:00:00', 'UTC'),
            'starting_cash' => 1000,
        ]);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'pos_shift_id' => $shift->id,
            'client_name' => 'Mina Cruz',
        ]);
        $movement = PosCashMovement::factory()->create([
            'pos_shift_id' => $shift->id,
            'type' => PosCashMovementType::CashIn,
            'amount' => 500,
            'reason' => 'Hotel downpayment for Mina Cruz',
            'recorded_at' => CarbonImmutable::parse('2026-06-15 02:00:00', 'UTC'),
        ]);
        HotelStayPayment::factory()->create([
            'hotel_stay_id' => $stay->id,
            'pos_shift_id' => $shift->id,
            'pos_cash_movement_id' => $movement->id,
            'amount' => '500.00',
            'recorded_at' => CarbonImmutable::parse('2026-06-15 02:00:00', 'UTC'),
        ]);

        $this->actingAsEmployee($viewer)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branchOrders.0.activities', fn ($rows) => $rows->contains(
                    fn ($row) => $row['title'] === 'Hotel payment'
                        && $row['detail'] === 'Mina Cruz'
                        && $row['amount_label'] === '₱500.00',
                ) && $rows->doesntContain(fn ($row) => $row['title'] === 'Cash in')));
    }

    public function test_calendar_shows_guests_after_they_check_out(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 04:00:00', 'UTC'));

        $manager = $this->employeeWith(['dashboard.view', 'staff.manage'], 'Ada Manager', '30001');
        $branch = Branch::factory()->create(['name' => 'North']);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-14',
            'hotel_pod_type_id' => HotelPodType::query()->where('name', 'Small dog')->value('id'),
        ]);
        HotelStayPet::factory()->create([
            'hotel_stay_id' => $stay->id,
            'name' => 'Biscuit',
        ]);
        HotelStayPayment::factory()->create([
            'hotel_stay_id' => $stay->id,
            'pos_shift_id' => $stay->pos_shift_id,
            'amount' => '250.00',
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Gone Guest',
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-16',
        ]);
        HotelStay::factory()->create([
            'client_name' => 'Later Guest',
            'checked_in_at' => '2026-07-02 02:00:00',
            'checkout_on' => '2026-07-04',
        ]);

        $this->actingAsEmployee($manager)
            ->get('/backoffice/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('schedule.guests', 2)
                ->where('schedule.guests.0.client', 'Mina Cruz')
                ->where('schedule.guests.0.pets', 'Biscuit')
                ->where('schedule.guests.0.pod_type', 'Small dog')
                ->where('schedule.guests.0.paid_label', '₱250.00')
                ->where('schedule.guests.0.branch', 'North')
                ->where('schedule.guests.0.dates', ['2026-06-14', '2026-06-15'])
                ->where('schedule.guests.1.client', 'Gone Guest')
                ->where('schedule.guests.1.dates', ['2026-06-14', '2026-06-15', '2026-06-16']));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function employeeWith(array $permissions, string $name = 'Sample Employee', string $number = '30001'): Employee
    {
        $role = Role::factory()->withPermissions($permissions)->create();

        return Employee::factory()->create([
            'role_id' => $role->id,
            'name' => $name,
            'employee_number' => $number,
        ]);
    }

    private function commission(Employee $employee, float $amount): void
    {
        PosSaleItemCommission::factory()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'amount' => $amount,
        ]);
    }
}
