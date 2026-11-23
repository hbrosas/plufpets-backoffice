<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\HotelStayPet;
use App\Models\PosShift;
use App\Models\Role;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HotelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_manage_hotel(): void
    {
        $this->get('/backoffice/hotel')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_a_pod_type(): void
    {
        $this->post('/backoffice/hotel/pod-types', [
            'name' => 'Crate',
            'is_active' => true,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('hotel_pod_types', ['name' => 'Crate']);
    }

    public function test_employee_without_hotel_access_cannot_open_manage_hotel(): void
    {
        $employee = Employee::factory()->create([
            'role_id' => Role::factory()->withPermissions(['dashboard.view'])->create()->id,
        ]);

        $this->actingAsEmployee($employee)
            ->get('/backoffice/hotel')
            ->assertForbidden();
    }

    public function test_empty_pod_type_name_is_rejected(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->post('/backoffice/hotel/pod-types', [])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'name' => 'Enter a pod type name.',
            ]);
    }

    public function test_pod_type_is_created(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->post('/backoffice/hotel/pod-types', [
                'name' => ' Crate ',
                'is_active' => true,
            ])
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Pod type created.');

        $this->assertDatabaseHas('hotel_pod_types', [
            'name' => 'Crate',
            'is_active' => true,
            'sort_order' => 6,
        ]);
    }

    public function test_duplicate_pod_type_name_is_rejected(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->post('/backoffice/hotel/pod-types', [
                'name' => 'Cat',
                'is_active' => true,
            ])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'name' => 'That pod type already exists.',
            ]);
    }

    public function test_pod_type_is_updated(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.pod-types.update', $podType), [
                'name' => 'Large crate',
                'is_active' => false,
            ])
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Pod type updated.');

        $this->assertDatabaseHas('hotel_pod_types', [
            'id' => $podType->id,
            'name' => 'Large crate',
            'is_active' => false,
        ]);
    }

    public function test_pod_type_can_be_saved_with_its_current_name(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate', 'is_active' => true]);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.pod-types.update', $podType), [
                'name' => 'Crate',
                'is_active' => false,
            ])
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Pod type updated.');

        $this->assertDatabaseHas('hotel_pod_types', [
            'id' => $podType->id,
            'name' => 'Crate',
            'is_active' => false,
        ]);
    }

    public function test_pod_type_cannot_be_renamed_to_an_existing_name(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.pod-types.update', $podType), [
                'name' => 'Cat',
                'is_active' => true,
            ])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'name' => 'That pod type already exists.',
            ]);

        $this->assertDatabaseHas('hotel_pod_types', [
            'id' => $podType->id,
            'name' => 'Crate',
        ]);
    }

    public function test_unused_pod_type_is_deleted(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->delete(route('hotel.pod-types.destroy', $podType))
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Pod type deleted.');

        $this->assertDatabaseMissing('hotel_pod_types', ['id' => $podType->id]);
    }

    public function test_pod_type_in_use_cannot_be_deleted(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);
        HotelStay::factory()->create(['hotel_pod_type_id' => $podType->id]);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->delete(route('hotel.pod-types.destroy', $podType))
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'pod_type' => 'This pod type is in use.',
            ]);

        $this->assertDatabaseHas('hotel_pod_types', ['id' => $podType->id]);
    }

    public function test_manage_hotel_lists_checked_in_guests_before_checked_out_guests(): void
    {
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);
        $branch = Branch::factory()->create(['name' => 'North']);
        $stay = HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
            'contact_number' => '09171234567',
            'hotel_pod_type_id' => $podType->id,
            'checked_in_at' => '2026-11-09 02:00:00',
            'checkout_on' => '2026-11-11',
        ]);
        HotelStayPet::factory()->create([
            'hotel_stay_id' => $stay->id,
            'name' => 'Biscuit',
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Gone Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_in_at' => '2026-11-09 06:00:00',
            'checkout_on' => '2026-11-12',
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/hotel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hotel/Index')
                ->has('stays', 1)
                ->where('stays.0.client_name', 'Mina Cruz')
                ->where('stays.0.contact_number', '09171234567')
                ->where('stays.0.pet_names', 'Biscuit')
                ->where('stays.0.pod_type_label', 'Crate')
                ->where('stays.0.branch_name', 'North')
                ->where('stays.0.checked_in_label', 'Nov 9, 2026 10:00 AM')
                ->where('stays.0.checkout_label', 'Nov 11, 2026')
                ->where('stays.0.checked_out_label', '')
                ->where('stays.0.payments_available_label', '₱0.00')
                ->where('filters.tab', 'current')
                ->missing('checkedOutStays')
                ->where('branches', fn ($branches) => $branches->contains(
                    fn ($branch) => $branch['name'] === 'North',
                ))
                ->where('podTypes', fn ($podTypes) => $podTypes->contains(
                    fn ($type) => $type['name'] === 'Small dog' && $type['is_active'] === true,
                )));
    }

    public function test_checked_out_guests_are_listed_newest_first(): void
    {
        $branch = Branch::factory()->create(['name' => 'North']);
        $podType = HotelPodType::factory()->create(['name' => 'Crate']);
        HotelStay::factory()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
            'hotel_pod_type_id' => $podType->id,
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Early Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_out_at' => '2026-06-15 03:00:00',
            'checked_out_by_name' => 'Hazel Brosas',
            'checked_out_by_employee_number' => '10001',
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Late Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_out_at' => '2026-06-15 10:00:00',
            'checked_out_by_name' => 'Robin Santiago',
            'checked_out_by_employee_number' => '10002',
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/hotel?tab=checked-out')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Hotel/Index')
                ->missing('stays')
                ->has('checkedOutStays', 2)
                ->where('filters.tab', 'checked-out')
                ->where('filters.from', '')
                ->where('filters.to', '')
                ->where('checkedOutStays.0.client_name', 'Late Guest')
                ->where('checkedOutStays.0.checked_out_label', 'Jun 15, 2026 6:00 PM')
                ->where('checkedOutStays.0.checked_out_by', 'Robin Santiago · #10002')
                ->where('checkedOutStays.0.branch_name', 'North')
                ->where('checkedOutStays.0.pod_type_label', 'Crate')
                ->where('checkedOutStays.1.client_name', 'Early Guest')
                ->where('checkedOutStays.1.checked_out_by', 'Hazel Brosas · #10001'));
    }

    public function test_checked_out_guests_can_be_filtered_by_checkout_date(): void
    {
        $branch = Branch::factory()->create();
        $podType = HotelPodType::factory()->create();
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Midnight Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_out_at' => '2026-06-14 16:00:00',
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Day Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_out_at' => '2026-06-15 10:00:00',
        ]);
        HotelStay::factory()->checkedOut()->create([
            'branch_id' => $branch->id,
            'client_name' => 'Next Day Guest',
            'hotel_pod_type_id' => $podType->id,
            'checked_out_at' => '2026-06-15 16:00:00',
        ]);

        $this->actingAsEmployee()
            ->get('/backoffice/hotel?tab=checked-out&from=2026-06-15&to=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-06-15')
                ->where('filters.to', '2026-06-15')
                ->has('checkedOutStays', 2)
                ->where('checkedOutStays.0.client_name', 'Day Guest')
                ->where('checkedOutStays.1.client_name', 'Midnight Guest'));

        $this->actingAsEmployee()
            ->get('/backoffice/hotel?tab=checked-out&from=2026-06-16&to=2026-06-15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-06-15')
                ->where('filters.to', '2026-06-16')
                ->has('checkedOutStays', 3)
                ->where('checkedOutStays.0.client_name', 'Next Day Guest'));
    }

    public function test_checked_out_tab_reloads_without_the_rest_of_the_page(): void
    {
        $this->actingAsEmployee()
            ->withHeaders([
                'X-Inertia-Partial-Component' => 'Hotel/Index',
                'X-Inertia-Partial-Data' => 'checkedOutStays,filters',
            ])
            ->get('/backoffice/hotel?tab=checked-out')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('checkedOutStays')
                ->where('filters.tab', 'checked-out')
                ->missing('stays')
                ->missing('options')
                ->missing('branches')
                ->missing('paymentMethods')
                ->missing('podTypes'));
    }

    public function test_guest_cannot_check_a_guest_in_from_the_backoffice(): void
    {
        $this->post('/backoffice/hotel/stays', [
            'branch_id' => Branch::factory()->create()->id,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('hotel_stays', 0);
    }

    public function test_backoffice_check_in_requires_a_branch(): void
    {
        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->post('/backoffice/hotel/stays', [])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'branch_id' => 'Choose a branch.',
                'client_name' => 'Enter the client name.',
            ]);
    }

    public function test_backoffice_check_in_requires_an_open_shift(): void
    {
        $branch = Branch::factory()->create();

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->post('/backoffice/hotel/stays', $this->checkInPayload($branch))
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'status' => 'Open a shift before checking a guest in.',
            ]);
    }

    public function test_backoffice_check_in_records_the_guest_on_the_open_shift(): void
    {
        $branch = Branch::factory()->create();
        PosShift::factory()->create(['branch_id' => $branch->id]);

        $this->actingAsEmployee()
            ->post('/backoffice/hotel/stays', $this->checkInPayload($branch))
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Checked in Mina Cruz.');

        $this->assertDatabaseHas('hotel_stays', [
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
        ]);
    }

    public function test_guest_cannot_change_hotel_stay_dates(): void
    {
        $stay = HotelStay::factory()->create([
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-15',
        ]);

        $this->put(route('hotel.stays.update', $stay), [
            'checked_in_at' => '2026-06-16T10:00',
            'checkout_on' => '2026-06-18',
        ])->assertRedirect(route('login'));

        $stay->refresh();
        $this->assertSame('2026-06-14 02:00:00', $stay->checked_in_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-15', $stay->checkout_on?->toDateString());
    }

    public function test_employee_without_hotel_access_cannot_change_hotel_stay_dates(): void
    {
        $employee = Employee::factory()->create([
            'role_id' => Role::factory()->withPermissions(['dashboard.view'])->create()->id,
        ]);
        $stay = HotelStay::factory()->create([
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-15',
        ]);

        $this->actingAsEmployee($employee)
            ->put(route('hotel.stays.update', $stay), [
                'checked_in_at' => '2026-06-16T10:00',
                'checkout_on' => '2026-06-18',
            ])
            ->assertForbidden();

        $this->assertSame('2026-06-15', $stay->fresh()->checkout_on?->toDateString());
    }

    public function test_stay_dates_can_be_changed(): void
    {
        $stay = HotelStay::factory()->create([
            'client_name' => 'Mina Cruz',
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-15',
        ]);

        $this->actingAsEmployee()
            ->put(route('hotel.stays.update', $stay), [
                'checked_in_at' => '2026-06-14T18:30',
                'checkout_on' => '2026-06-14',
                'checked_out_at' => '2026-06-16T09:15',
            ])
            ->assertRedirect(route('hotel.index'))
            ->assertSessionHas('status', 'Stay dates updated.');

        $stay->refresh();
        $this->assertSame('2026-06-14 10:30:00', $stay->checked_in_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-14', $stay->checkout_on?->toDateString());
        $this->assertNull($stay->checked_out_at);

        $this->actingAsEmployee()
            ->get('/backoffice/hotel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stays.0.checked_in_at', '2026-06-14T18:30')
                ->where('stays.0.checked_in_label', 'Jun 14, 2026 6:30 PM')
                ->where('stays.0.checkout_on', '2026-06-14')
                ->where('stays.0.checkout_label', 'Jun 14, 2026'));
    }

    public function test_checked_out_stay_dates_can_be_changed(): void
    {
        $stay = HotelStay::factory()->checkedOut()->create([
            'client_name' => 'Mina Cruz',
            'checked_in_at' => '2026-06-14 02:00:00',
            'checkout_on' => '2026-06-15',
            'checked_out_at' => '2026-06-15 02:00:00',
            'checked_out_by_name' => 'Hazel Brosas',
            'checked_out_by_employee_number' => '10001',
        ]);

        $this->actingAsEmployee()
            ->put(route('hotel.stays.update', $stay).'?tab=checked-out&from=2026-06-16&to=2026-06-16', [
                'checked_in_at' => '2026-06-14T18:30',
                'checkout_on' => '2026-06-16',
                'checked_out_at' => '2026-06-16T09:15',
            ])
            ->assertRedirect(route('hotel.index', [
                'tab' => 'checked-out',
                'from' => '2026-06-16',
                'to' => '2026-06-16',
            ]))
            ->assertSessionHas('status', 'Stay dates updated.');

        $stay->refresh();
        $this->assertSame('2026-06-16 01:15:00', $stay->checked_out_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Hazel Brosas', $stay->checked_out_by_name);

        $this->actingAsEmployee()
            ->get('/backoffice/hotel?tab=checked-out&from=2026-06-16&to=2026-06-16')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('checkedOutStays.0.checked_out_at', '2026-06-16T09:15')
                ->where('checkedOutStays.0.checked_out_label', 'Jun 16, 2026 9:15 AM')
                ->where('checkedOutStays.0.checked_out_by', 'Hazel Brosas · #10001'));
    }

    public function test_stay_dates_require_a_check_in_and_check_out(): void
    {
        $stay = HotelStay::factory()->create();

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.stays.update', $stay), [])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'checked_in_at' => 'Enter the check-in date.',
                'checkout_on' => 'Enter the check-out date.',
            ]);
    }

    public function test_check_out_date_before_check_in_is_rejected(): void
    {
        $stay = HotelStay::factory()->create([
            'checkout_on' => '2026-06-16',
        ]);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.stays.update', $stay), [
                'checked_in_at' => '2026-06-16T10:00',
                'checkout_on' => '2026-06-15',
            ])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'checkout_on' => 'Check-out date must be on or after the check-in date.',
            ]);

        $this->assertSame('2026-06-16', $stay->fresh()->checkout_on?->toDateString());
    }

    public function test_checked_out_time_before_check_in_is_rejected(): void
    {
        $stay = HotelStay::factory()->checkedOut()->create([
            'checked_out_at' => '2026-06-16 04:00:00',
        ]);

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.stays.update', $stay), [
                'checked_in_at' => '2026-06-16T12:00',
                'checkout_on' => '2026-06-16',
                'checked_out_at' => '2026-06-16T11:59',
            ])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'checked_out_at' => 'Checked-out time must be on or after check-in.',
            ]);

        $this->assertSame('2026-06-16 04:00:00', $stay->fresh()->checked_out_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_checked_out_guest_requires_a_checked_out_time(): void
    {
        $stay = HotelStay::factory()->checkedOut()->create();

        $this->actingAsEmployee()
            ->from('/backoffice/hotel')
            ->put(route('hotel.stays.update', $stay), [
                'checked_in_at' => '2026-06-16T10:00',
                'checkout_on' => '2026-06-16',
            ])
            ->assertRedirect('/backoffice/hotel')
            ->assertSessionHasErrors([
                'checked_out_at' => 'Enter the checked-out date.',
            ]);

        $this->assertNotNull($stay->fresh()->checked_out_at);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkInPayload(Branch $branch): array
    {
        return [
            'branch_id' => $branch->id,
            'client_name' => 'Mina Cruz',
            'contact_number' => '09171234567',
            'address' => '12 Mabini Street, Quezon City',
            'checkout_on' => now(ReportPeriod::ZONE)->addDay()->toDateString(),
            'hotel_pod_type_id' => HotelPodType::query()->where('name', 'Small dog')->value('id'),
            'pets' => [[
                'name' => 'Biscuit',
                'breed' => 'Aspin',
                'age' => '3 years',
                'gender' => 'female',
                'has_vaccine_booklet' => true,
                'vaccines' => [],
                'feeding_instructions' => null,
                'medication_instructions' => null,
                'special_instructions' => null,
            ]],
        ];
    }
}
