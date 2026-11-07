<?php

namespace App\Support;

use App\Models\Holiday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PayrollCalendar
{
    /**
     * Ordinary days skip Sunday, the weekly day off, and any holiday.
     * A holiday that falls on Sunday is already a day off.
     *
     * @param  list<string>  $holidayDates
     */
    public static function ordinaryDays(string $periodStart, string $periodEnd, array $holidayDates): int
    {
        return count(self::ordinaryDates($periodStart, $periodEnd, $holidayDates));
    }

    /**
     * @param  list<string>  $holidayDates
     * @return list<string>
     */
    public static function ordinaryDates(string $periodStart, string $periodEnd, array $holidayDates): array
    {
        $holidays = array_fill_keys($holidayDates, true);
        $cursor = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->startOfDay();
        $dates = [];

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();

            if (! $cursor->isSunday() && ! isset($holidays[$date])) {
                $dates[] = $date;
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Ordinary days are selected dates that are not holidays. Holiday days are the selected holiday dates.
     *
     * @param  list<mixed>  $dates
     * @param  iterable<int, Holiday>  $holidays
     * @return array{days_worked: string, worked_holiday_ids: list<int>, worked_dates: list<string>}
     */
    public static function attendanceFromDates(string $periodStart, string $periodEnd, array $dates, iterable $holidays): array
    {
        $start = Carbon::parse($periodStart)->toDateString();
        $end = Carbon::parse($periodEnd)->toDateString();
        $byDate = Collection::make($holidays)->mapWithKeys(
            fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->id],
        );
        $worked = [];
        $counted = [];
        $ordinary = 0;
        $holidayIds = [];

        foreach ($dates as $date) {
            if (! is_string($date) || trim($date) === '') {
                continue;
            }

            try {
                $normalized = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                $worked[] = $date;

                continue;
            }

            $worked[] = $normalized;

            if (isset($counted[$normalized]) || $normalized < $start || $normalized > $end) {
                continue;
            }

            $counted[$normalized] = true;
            $holidayId = $byDate->get($normalized);

            if ($holidayId !== null) {
                $holidayIds[] = (int) $holidayId;
            } else {
                $ordinary++;
            }
        }

        return [
            'days_worked' => (string) $ordinary,
            'worked_holiday_ids' => $holidayIds,
            'worked_dates' => $worked,
        ];
    }

    /**
     * @param  iterable<int, Holiday>  $holidays
     * @return list<array{
     *     date: string,
     *     day: int,
     *     weekday: int,
     *     weekday_label: string,
     *     is_day_off: bool,
     *     holiday: array{id: int, name: string, type_label: string}|null
     * }>
     */
    public static function days(string $periodStart, string $periodEnd, iterable $holidays): array
    {
        $byDate = [];

        foreach ($holidays as $holiday) {
            $byDate[$holiday->date->toDateString()] = [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'type_label' => $holiday->type->label(),
            ];
        }

        $cursor = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->startOfDay();
        $days = [];

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $days[] = [
                'date' => $date,
                'day' => $cursor->day,
                'weekday' => $cursor->dayOfWeek,
                'weekday_label' => $cursor->format('D'),
                'is_day_off' => $cursor->isSunday(),
                'holiday' => $byDate[$date] ?? null,
            ];
            $cursor->addDay();
        }

        return $days;
    }
}
