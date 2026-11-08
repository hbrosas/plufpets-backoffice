<?php

namespace App\Models;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use Carbon\CarbonImmutable;
use Database\Factories\DeliveryScheduleFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'kind', 'frequency', 'weekdays', 'scheduled_on', 'starts_at', 'is_active'])]
class DeliverySchedule extends Model
{
    /** @use HasFactory<DeliveryScheduleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'date',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DeliveryScheduleKind::class,
            'frequency' => DeliveryScheduleFrequency::class,
            'weekdays' => 'array',
            'scheduled_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<PosOpenOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(PosOpenOrder::class);
    }

    public function label(): string
    {
        $time = $this->clockLabel($this->starts_at);

        if ($this->kind === DeliveryScheduleKind::Frequency) {
            $when = $this->frequency === DeliveryScheduleFrequency::Weekly
                ? 'Every '.$this->weekdayNames()
                : 'Daily';

            return $when.' · '.$time;
        }

        return $this->scheduled_on?->format('M j, Y').' · '.$time;
    }

    public function isAssignable(): bool
    {
        return $this->is_active && ! $this->hasEnded();
    }

    public function isPreferredSlotFor(int $branchId): bool
    {
        return $this->branch_id === $branchId && $this->isAssignable();
    }

    /**
     * @return list<array{id: int, on: string, date: string, time: string, day: string}>
     */
    public static function preferredSlots(int $branchId): array
    {
        return static::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('scheduled_on')
                    ->orWhere('scheduled_on', '>=', now('Asia/Manila')->toDateString());
            })
            ->orderBy('id')
            ->get()
            ->flatMap(function (self $schedule): array {
                return array_map(fn (CarbonImmutable $occurrence): array => [
                    'id' => $schedule->id,
                    'on' => $occurrence->toDateString(),
                    'date' => $occurrence->format('M j, Y'),
                    'time' => $occurrence->format('g:i A'),
                    'day' => $occurrence->format('l'),
                    'sort' => $occurrence->getTimestamp(),
                ], $schedule->upcomingOccurrences(6));
            })
            ->sortBy([
                ['sort', 'asc'],
                ['id', 'asc'],
            ])
            ->take(6)
            ->map(fn (array $slot): array => [
                'id' => $slot['id'],
                'on' => $slot['on'],
                'date' => $slot['date'],
                'time' => $slot['time'],
                'day' => $slot['day'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function upcomingOccurrences(int $limit = 6): array
    {
        if ($limit < 1 || ! $this->isAssignable()) {
            return [];
        }

        $now = CarbonImmutable::now('Asia/Manila');
        $time = $this->clock($this->starts_at);

        if ($this->kind === DeliveryScheduleKind::Date) {
            if ($this->scheduled_on === null) {
                return [];
            }

            $occurrence = CarbonImmutable::parse($this->scheduled_on->toDateString().' '.$time, 'Asia/Manila');

            return $occurrence->greaterThan($now) ? [$occurrence] : [];
        }

        if ($this->frequency === DeliveryScheduleFrequency::Weekly) {
            $days = collect($this->weekdays ?? [])
                ->map(fn (mixed $day): int => (int) $day)
                ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
                ->unique();

            if ($days->isEmpty()) {
                return [];
            }

            $occurrences = [];

            for ($offset = 0; $offset <= $limit * 7 && count($occurrences) < $limit; $offset++) {
                $day = $now->startOfDay()->addDays($offset);

                if (! $days->contains($day->dayOfWeekIso)) {
                    continue;
                }

                $occurrence = CarbonImmutable::parse($day->toDateString().' '.$time, 'Asia/Manila');

                if ($occurrence->greaterThan($now)) {
                    $occurrences[] = $occurrence;
                }
            }

            return $occurrences;
        }

        if ($this->frequency !== DeliveryScheduleFrequency::Daily) {
            return [];
        }

        $cursor = CarbonImmutable::parse($now->toDateString().' '.$time, 'Asia/Manila');

        if ($cursor->lessThanOrEqualTo($now)) {
            $cursor = $cursor->addDay();
        }

        $occurrences = [];

        for ($index = 0; $index < $limit; $index++) {
            $occurrences[] = $cursor->addDays($index);
        }

        return $occurrences;
    }

    public function includesDate(string $date): bool
    {
        $day = CarbonImmutable::parse($date, 'Asia/Manila')->startOfDay();
        $occurrence = CarbonImmutable::parse($day->toDateString().' '.$this->clock($this->starts_at), 'Asia/Manila');

        if ($occurrence->lessThanOrEqualTo(CarbonImmutable::now('Asia/Manila'))) {
            return false;
        }

        if ($this->kind === DeliveryScheduleKind::Date) {
            return $this->scheduled_on?->toDateString() === $day->toDateString();
        }

        if ($this->frequency === DeliveryScheduleFrequency::Weekly) {
            return collect($this->weekdays ?? [])
                ->map(fn (mixed $weekday): int => (int) $weekday)
                ->contains($day->dayOfWeekIso);
        }

        return $this->frequency === DeliveryScheduleFrequency::Daily;
    }

    public function timeLabel(): string
    {
        return $this->clockLabel($this->starts_at);
    }

    public function hasEnded(): bool
    {
        if ($this->kind !== DeliveryScheduleKind::Date || $this->scheduled_on === null) {
            return false;
        }

        return CarbonImmutable::parse(
            $this->scheduled_on->toDateString().' '.$this->clock($this->starts_at),
            'Asia/Manila',
        )->lessThanOrEqualTo(CarbonImmutable::now('Asia/Manila'));
    }

    /**
     * @param  list<int>|null  $weekdays
     */
    public function sameOccurrence(?string $date, ?DeliveryScheduleFrequency $frequency, ?array $weekdays): bool
    {
        $days = collect($weekdays ?? [])->map(fn (mixed $day): int => (int) $day)->sort()->values()->all();
        $storedDays = collect($this->weekdays ?? [])->map(fn (mixed $day): int => (int) $day)->sort()->values()->all();

        return $this->scheduled_on?->toDateString() === $date
            && $this->frequency === $frequency
            && $storedDays === $days;
    }

    private function weekdayNames(): string
    {
        $names = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return collect($this->weekdays ?? [])
            ->map(fn (mixed $day): string => $names[(int) $day] ?? '')
            ->filter()
            ->implode(', ');
    }

    private function clockLabel(mixed $time): string
    {
        return CarbonImmutable::parse($this->clock($time), 'Asia/Manila')->format('g:i A');
    }

    private function clock(mixed $time): string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('H:i:s');
        }

        $value = substr(trim((string) $time), 0, 8);

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
