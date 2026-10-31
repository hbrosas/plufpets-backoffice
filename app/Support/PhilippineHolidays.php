<?php

namespace App\Support;

use App\Enums\HolidayType;
use Illuminate\Support\Carbon;

class PhilippineHolidays
{
    /**
     * Statutory regular holidays and the special non-working days observed every year.
     * Eid dates are proclaimed annually, so they are added on the calendar when proclaimed.
     * Chinese New Year is included for years with a known date.
     *
     * @return list<array{date: string, name: string, type: HolidayType}>
     */
    public static function forYear(int $year): array
    {
        $easter = self::easterSunday($year);
        $heroesDay = Carbon::create($year, 8, 31)->startOfDay();

        while ($heroesDay->dayOfWeek !== Carbon::MONDAY) {
            $heroesDay->subDay();
        }

        $holidays = [
            self::holiday($year, 1, 1, "New Year's Day", HolidayType::Regular),
            self::holiday($year, 4, 9, 'Araw ng Kagitingan', HolidayType::Regular),
            self::onDate($easter->copy()->subDays(3), 'Maundy Thursday', HolidayType::Regular),
            self::onDate($easter->copy()->subDays(2), 'Good Friday', HolidayType::Regular),
            self::onDate($easter->copy()->subDay(), 'Black Saturday', HolidayType::Special),
            self::holiday($year, 5, 1, 'Labor Day', HolidayType::Regular),
            self::holiday($year, 6, 12, 'Independence Day', HolidayType::Regular),
            self::holiday($year, 8, 21, 'Ninoy Aquino Day', HolidayType::Special),
            self::onDate($heroesDay, 'National Heroes Day', HolidayType::Regular),
            self::holiday($year, 11, 1, "All Saints' Day", HolidayType::Special),
            self::holiday($year, 11, 30, 'Bonifacio Day', HolidayType::Regular),
            self::holiday($year, 12, 8, 'Feast of the Immaculate Conception', HolidayType::Special),
            self::holiday($year, 12, 24, 'Christmas Eve', HolidayType::Special),
            self::holiday($year, 12, 25, 'Christmas Day', HolidayType::Regular),
            self::holiday($year, 12, 30, 'Rizal Day', HolidayType::Regular),
            self::holiday($year, 12, 31, 'Last Day of the Year', HolidayType::Special),
        ];

        $chineseNewYear = self::chineseNewYear($year);

        if ($chineseNewYear instanceof Carbon) {
            $holidays[] = self::onDate($chineseNewYear, 'Chinese New Year', HolidayType::Special);
        }

        usort($holidays, fn (array $left, array $right): int => $left['date'] <=> $right['date']);

        return $holidays;
    }

    /**
     * @return array{date: string, name: string, type: HolidayType}
     */
    private static function holiday(int $year, int $month, int $day, string $name, HolidayType $type): array
    {
        return self::onDate(Carbon::create($year, $month, $day)->startOfDay(), $name, $type);
    }

    /**
     * @return array{date: string, name: string, type: HolidayType}
     */
    private static function onDate(Carbon $date, string $name, HolidayType $type): array
    {
        return [
            'date' => $date->toDateString(),
            'name' => $name,
            'type' => $type,
        ];
    }

    private static function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private static function chineseNewYear(int $year): ?Carbon
    {
        $dates = [
            2024 => '2024-02-10',
            2025 => '2025-01-29',
            2026 => '2026-02-17',
            2027 => '2027-02-06',
            2028 => '2028-01-26',
            2029 => '2029-02-13',
            2030 => '2030-02-03',
            2031 => '2031-01-23',
            2032 => '2032-02-11',
            2033 => '2033-01-31',
            2034 => '2034-02-19',
            2035 => '2035-02-08',
        ];

        $date = $dates[$year] ?? null;

        return $date === null ? null : Carbon::parse($date)->startOfDay();
    }
}
