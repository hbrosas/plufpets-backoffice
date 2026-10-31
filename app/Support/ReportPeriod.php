<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ReportPeriod
{
    public const ZONE = 'Asia/Manila';

    public function __construct(
        public string $key,
        public string $label,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function today(): self
    {
        $now = self::now();

        return self::between($now->startOfDay(), $now->endOfDay(), 'today', 'Today');
    }

    public static function month(): self
    {
        $now = self::now();

        return self::between($now->startOfMonth(), $now->endOfMonth(), 'month', 'This month');
    }

    public static function monthToDate(): self
    {
        $now = self::now();

        return self::between($now->startOfMonth(), $now->endOfDay(), 'month', 'This month');
    }

    public static function quarter(): self
    {
        $now = self::now();

        return self::between($now->startOfQuarter(), $now->endOfQuarter(), 'quarter', 'This quarter');
    }

    public static function week(): self
    {
        $now = self::now();

        return self::between(
            $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay(),
            $now->endOfDay(),
            'week',
            'This week',
        );
    }

    public static function twoWeeks(): self
    {
        $now = self::now();

        return self::between($now->subDays(13)->startOfDay(), $now->endOfDay(), 'two_weeks', 'Last 2 weeks');
    }

    public static function custom(string $from, string $to): self
    {
        $start = CarbonImmutable::parse($from, self::ZONE)->startOfDay();
        $end = CarbonImmutable::parse($to, self::ZONE)->endOfDay();
        $label = $start->isSameDay($end)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');

        return self::between($start, $end, 'custom', $label);
    }

    public static function day(): self
    {
        $now = self::now();

        return self::between($now->startOfDay(), $now->endOfDay(), 'day', 'Today');
    }

    public static function lastWeekDay(): self
    {
        $then = self::now()->subWeek();

        return self::between($then->startOfDay(), $then->endOfDay(), 'last_week', 'Same day last week');
    }

    public static function year(): self
    {
        $now = self::now();

        return self::between($now->startOfYear(), $now->endOfYear(), 'year', 'This year');
    }

    public static function report(string $preset, ?string $from, ?string $to): self
    {
        return match ($preset) {
            'day' => self::day(),
            'week' => self::week(),
            'year' => self::year(),
            'custom' => self::custom((string) $from, (string) $to),
            default => self::month(),
        };
    }

    public static function commission(string $preset, ?string $from, ?string $to): self
    {
        return match ($preset) {
            'week' => self::week(),
            'two_weeks' => self::twoWeeks(),
            'custom' => self::custom((string) $from, (string) $to),
            default => self::monthToDate(),
        };
    }

    /**
     * @return array{preset: string, from: string, to: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->key,
            'from' => $this->from->timezone(self::ZONE)->toDateString(),
            'to' => $this->to->timezone(self::ZONE)->toDateString(),
            'label' => $this->label,
        ];
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONE);
    }

    private static function between(CarbonImmutable $start, CarbonImmutable $end, string $key, string $label): self
    {
        return new self($key, $label, $start->utc(), $end->utc());
    }
}
