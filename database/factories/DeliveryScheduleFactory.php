<?php

namespace Database\Factories;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use App\Models\Branch;
use App\Models\DeliverySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverySchedule>
 */
class DeliveryScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'kind' => DeliveryScheduleKind::Date,
            'frequency' => null,
            'weekdays' => null,
            'scheduled_on' => now('Asia/Manila')->addDay()->toDateString(),
            'starts_at' => '09:00:00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => DeliveryScheduleKind::Date,
            'frequency' => null,
            'weekdays' => null,
            'scheduled_on' => now('Asia/Manila')->subDay()->toDateString(),
            'starts_at' => '09:00:00',
            'is_active' => true,
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => DeliveryScheduleKind::Frequency,
            'frequency' => DeliveryScheduleFrequency::Daily,
            'weekdays' => null,
            'scheduled_on' => null,
        ]);
    }

    /**
     * @param  list<int>  $weekdays
     */
    public function weekly(array $weekdays = [1, 3]): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => DeliveryScheduleKind::Frequency,
            'frequency' => DeliveryScheduleFrequency::Weekly,
            'weekdays' => $weekdays,
            'scheduled_on' => null,
        ]);
    }
}
