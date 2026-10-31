<?php

namespace Tests\Feature;

use App\Enums\HolidayType;
use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HolidayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_holidays(): void
    {
        $this->get('/backoffice/staff/holidays')->assertRedirect(route('login'));
        $this->post('/backoffice/staff/holidays')->assertRedirect(route('login'));
        $this->post('/backoffice/staff/holidays/import')->assertRedirect(route('login'));
    }

    public function test_employee_without_staff_access_cannot_manage_holidays(): void
    {
        $this->actingAsEmployee()
            ->get('/backoffice/staff/holidays')
            ->assertOk();

        $employee = Employee::factory()->create();

        $this->actingAsEmployee($employee)
            ->get('/backoffice/staff/holidays')
            ->assertForbidden();

        $this->actingAsEmployee($employee)
            ->post('/backoffice/staff/holidays', [
                'date' => '2026-12-25',
                'name' => 'Christmas Day',
                'type' => HolidayType::Regular->value,
            ])
            ->assertForbidden();
    }

    public function test_holiday_can_be_added_and_deleted(): void
    {
        $this->actingAsEmployee()
            ->post('/backoffice/staff/holidays', [
                'date' => '2026-12-25',
                'name' => 'Christmas Day',
                'type' => HolidayType::Regular->value,
            ])
            ->assertRedirect(route('staff.holidays.index', ['year' => 2026]))
            ->assertSessionHas('status', 'Holiday added.');

        $holiday = Holiday::query()->firstOrFail();
        $this->assertSame('2026-12-25', $holiday->date->toDateString());
        $this->assertSame(HolidayType::Regular, $holiday->type);

        $this->actingAsEmployee()
            ->get(route('staff.holidays.index', ['year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Holidays/Index')
                ->where('holidays.0.name', 'Christmas Day')
                ->where('holidays.0.worked_label', '200% if worked')
                ->where('holidays.0.unworked_label', '100% if not worked'));

        $this->actingAsEmployee()
            ->delete("/backoffice/staff/holidays/{$holiday->id}")
            ->assertRedirect(route('staff.holidays.index', ['year' => 2026]))
            ->assertSessionHas('status', 'Holiday deleted.');

        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_duplicate_holiday_date_is_rejected(): void
    {
        Holiday::factory()->create([
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
        ]);

        $this->actingAsEmployee()
            ->from('/backoffice/staff/holidays')
            ->post('/backoffice/staff/holidays', [
                'date' => '2026-12-25',
                'name' => 'Another Christmas',
                'type' => HolidayType::Special->value,
            ])
            ->assertRedirect('/backoffice/staff/holidays')
            ->assertSessionHasErrors([
                'date' => 'That date already has a holiday.',
            ]);
    }

    public function test_philippine_holidays_can_be_imported_without_replacing_existing_dates(): void
    {
        Holiday::factory()->create([
            'date' => '2026-01-01',
            'name' => 'Custom New Year',
            'type' => HolidayType::Special,
        ]);

        $this->actingAsEmployee()
            ->post('/backoffice/staff/holidays/import', ['year' => 2026])
            ->assertRedirect(route('staff.holidays.index', ['year' => 2026]));

        $this->assertDatabaseCount('holidays', 17);
        $this->assertHoliday('2026-01-01', 'Custom New Year', HolidayType::Special);
        $this->assertHoliday('2026-02-17', 'Chinese New Year', HolidayType::Special);
        $this->assertHoliday('2026-04-02', 'Maundy Thursday', HolidayType::Regular);
        $this->assertHoliday('2026-04-03', 'Good Friday', HolidayType::Regular);
        $this->assertHoliday('2026-04-04', 'Black Saturday', HolidayType::Special);
        $this->assertHoliday('2026-08-21', 'Ninoy Aquino Day', HolidayType::Special);
        $this->assertHoliday('2026-08-31', 'National Heroes Day', HolidayType::Regular);
        $this->assertHoliday('2026-12-24', 'Christmas Eve', HolidayType::Special);
        $this->assertHoliday('2026-12-25', 'Christmas Day', HolidayType::Regular);

        $this->actingAsEmployee()
            ->post('/backoffice/staff/holidays/import', ['year' => 2026])
            ->assertRedirect(route('staff.holidays.index', ['year' => 2026]))
            ->assertSessionHas('status', 'Imported 0 Philippine holidays for 2026. 17 dates were already on the calendar.');

        $this->assertDatabaseCount('holidays', 17);
    }

    private function assertHoliday(string $date, string $name, HolidayType $type): void
    {
        $holiday = Holiday::query()->whereDate('date', $date)->first();

        $this->assertNotNull($holiday);
        $this->assertSame($name, $holiday->name);
        $this->assertSame($type, $holiday->type);
    }
}
