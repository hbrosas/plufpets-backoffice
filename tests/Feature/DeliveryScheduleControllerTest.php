<?php

namespace Tests\Feature;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use App\Models\Branch;
use App\Models\DeliverySchedule;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_delivery_schedule(): void
    {
        $response = $this->post('/backoffice/shop/delivery-schedules', [
            'scheduled_on' => '2026-11-09',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('delivery_schedules', 0);
    }

    public function test_employee_without_permission_cannot_create_a_delivery_schedule(): void
    {
        $response = $this->actingAsEmployee(Employee::factory()->create())
            ->post('/backoffice/shop/delivery-schedules', [
                'scheduled_on' => '2026-11-09',
                'starts_at' => '09:00',
                'ends_at' => '12:00',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('delivery_schedules', 0);
    }

    public function test_creating_a_schedule_requires_a_branch_a_type_and_a_time_slot(): void
    {
        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', []);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'branch_id' => 'Choose a branch.',
            'kind' => 'Choose by date or by frequency.',
            'slots' => 'Add a time slot.',
        ]);
        $this->assertDatabaseCount('delivery_schedules', 0);
    }

    public function test_creating_a_schedule_rejects_a_time_that_has_passed(): void
    {
        $this->freezeMorning();
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'date',
                'scheduled_on' => '2026-11-08',
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'slots.0.time' => 'Choose a time that has not passed.',
        ]);
        $this->assertDatabaseCount('delivery_schedules', 0);
    }

    public function test_creating_a_schedule_rejects_a_duplicate_time(): void
    {
        $this->freezeMorning();
        $schedule = DeliverySchedule::factory()->create([
            'scheduled_on' => '2026-11-09',
            'starts_at' => '09:00:00',
        ]);

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $schedule->branch_id,
                'kind' => 'date',
                'scheduled_on' => '2026-11-09',
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'slots.0.time' => 'That time slot already exists for this branch.',
        ]);
        $this->assertDatabaseCount('delivery_schedules', 1);
    }

    public function test_creating_future_times_saves_an_active_schedule(): void
    {
        $this->freezeMorning();
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'date',
                'scheduled_on' => '2026-11-09',
                'slots' => [
                    ['time' => '09:00'],
                    ['time' => '13:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHas('status', 'Delivery schedule created.');
        $schedules = DeliverySchedule::query()->orderBy('starts_at')->get();
        $this->assertCount(2, $schedules);
        $this->assertSame($branch->id, $schedules[0]->branch_id);
        $this->assertSame(DeliveryScheduleKind::Date, $schedules[0]->kind);
        $this->assertSame('2026-11-09', $schedules[0]->scheduled_on->toDateString());
        $this->assertSame('09:00:00', substr((string) $schedules[0]->starts_at, 0, 8));
        $this->assertSame('Nov 9, 2026 · 9:00 AM', $schedules[0]->label());
        $this->assertTrue($schedules[0]->is_active);
        $this->assertSame('13:00:00', substr((string) $schedules[1]->starts_at, 0, 8));
        $this->assertSame('Nov 9, 2026 · 1:00 PM', $schedules[1]->label());
    }

    public function test_the_same_time_slot_can_be_created_for_another_branch(): void
    {
        $this->freezeMorning();
        $existing = DeliverySchedule::factory()->create([
            'scheduled_on' => '2026-11-09',
            'starts_at' => '09:00:00',
        ]);
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'date',
                'scheduled_on' => '2026-11-09',
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHas('status', 'Delivery schedule created.');
        $created = DeliverySchedule::query()->where('branch_id', $branch->id)->sole();
        $this->assertSame($existing->branch_id, $existing->fresh()->branch_id);
        $this->assertSame('2026-11-09', $created->scheduled_on->toDateString());
        $this->assertSame('09:00:00', substr((string) $created->starts_at, 0, 8));
    }

    public function test_a_weekly_schedule_requires_at_least_one_day(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'frequency',
                'frequency' => 'weekly',
                'weekdays' => [],
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'weekdays' => 'Choose at least one day.',
        ]);
        $this->assertDatabaseCount('delivery_schedules', 0);
    }

    public function test_a_daily_frequency_saves_a_repeating_time_slot(): void
    {
        $branch = Branch::factory()->create(['name' => 'Greenheights']);

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'frequency',
                'frequency' => 'daily',
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHas('status', 'Delivery schedule created.');
        $schedule = DeliverySchedule::query()->sole();
        $this->assertSame(DeliveryScheduleKind::Frequency, $schedule->kind);
        $this->assertSame(DeliveryScheduleFrequency::Daily, $schedule->frequency);
        $this->assertNull($schedule->scheduled_on);
        $this->assertTrue($schedule->isAssignable());
        $this->assertSame('Daily · 9:00 AM', $schedule->label());
        $this->assertSame($branch->id, $schedule->branch_id);
    }

    public function test_a_weekly_frequency_saves_the_selected_days(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->post('/backoffice/shop/delivery-schedules', [
                'branch_id' => $branch->id,
                'kind' => 'frequency',
                'frequency' => 'weekly',
                'weekdays' => [3, 1],
                'slots' => [
                    ['time' => '09:00'],
                ],
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $schedule = DeliverySchedule::query()->sole();
        $this->assertSame(DeliveryScheduleFrequency::Weekly, $schedule->frequency);
        $this->assertSame([1, 3], collect($schedule->weekdays)->map(fn (mixed $day): int => (int) $day)->sort()->values()->all());
        $this->assertSame('Every Monday, Wednesday · 9:00 AM', $schedule->label());
    }

    public function test_guest_cannot_close_a_delivery_schedule(): void
    {
        $schedule = DeliverySchedule::factory()->create();

        $response = $this->put('/backoffice/shop/delivery-schedules/'.$schedule->id, [
            'is_active' => false,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue($schedule->fresh()->is_active);
    }

    public function test_employee_without_permission_cannot_close_a_delivery_schedule(): void
    {
        $schedule = DeliverySchedule::factory()->create();

        $response = $this->actingAsEmployee(Employee::factory()->create())
            ->put('/backoffice/shop/delivery-schedules/'.$schedule->id, [
                'is_active' => false,
            ]);

        $response->assertForbidden();
        $this->assertTrue($schedule->fresh()->is_active);
    }

    public function test_closing_a_schedule_requires_the_active_flag(): void
    {
        $this->freezeMorning();
        $schedule = DeliverySchedule::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->put('/backoffice/shop/delivery-schedules/'.$schedule->id, []);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'is_active' => 'Choose whether the delivery schedule is active.',
        ]);
        $this->assertTrue($schedule->fresh()->is_active);
    }

    public function test_closing_a_schedule_keeps_it_off_the_assign_list(): void
    {
        $this->freezeMorning();
        $schedule = DeliverySchedule::factory()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->put('/backoffice/shop/delivery-schedules/'.$schedule->id, [
                'is_active' => false,
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHas('status', 'Delivery schedule closed.');
        $this->assertFalse($schedule->fresh()->is_active);
    }

    public function test_reopening_a_schedule_makes_it_active_again(): void
    {
        $this->freezeMorning();
        $schedule = DeliverySchedule::factory()->inactive()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->put('/backoffice/shop/delivery-schedules/'.$schedule->id, [
                'is_active' => true,
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHas('status', 'Delivery schedule reopened.');
        $this->assertTrue($schedule->fresh()->is_active);
    }

    public function test_reopening_an_ended_schedule_is_rejected(): void
    {
        $this->freezeMorning();
        $schedule = DeliverySchedule::factory()->ended()->inactive()->create();

        $response = $this->actingAsEmployee()
            ->from(route('shop.orders.index'))
            ->put('/backoffice/shop/delivery-schedules/'.$schedule->id, [
                'is_active' => true,
            ]);

        $response->assertRedirect(route('shop.orders.index'));
        $response->assertSessionHasErrors([
            'is_active' => 'That delivery schedule has ended.',
        ]);
        $this->assertFalse($schedule->fresh()->is_active);
    }

    private function freezeMorning(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 02:00:00', 'UTC'));
    }
}
