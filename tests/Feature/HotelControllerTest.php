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
                ->where('stays.0.payments_available_label', '₱0.00')
                ->where('branches', fn ($branches) => $branches->contains(
                    fn ($branch) => $branch['name'] === 'North',
                ))
                ->where('podTypes', fn ($podTypes) => $podTypes->contains(
                    fn ($type) => $type['name'] === 'Small dog' && $type['is_active'] === true,
                )));
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
