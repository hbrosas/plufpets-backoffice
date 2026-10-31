<?php

namespace App\Enums;

enum HolidayType: string
{
    case Regular = 'regular';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Special => 'Special',
        };
    }

    /**
     * Share of the basic daily wage paid when the employee works the holiday.
     * Regular holidays are 200%. Special non-working days are 130%.
     */
    public function workedMultiplier(): float
    {
        return match ($this) {
            self::Regular => 2.0,
            self::Special => 1.3,
        };
    }

    /**
     * Share of the basic daily wage paid when the employee does not work.
     * Regular holidays are paid at 100%. Special non-working days follow no-work, no-pay.
     */
    public function unworkedMultiplier(): float
    {
        return match ($this) {
            self::Regular => 1.0,
            self::Special => 0.0,
        };
    }

    public function workedLabel(): string
    {
        return match ($this) {
            self::Regular => '200% if worked',
            self::Special => '130% if worked',
        };
    }

    public function unworkedLabel(): string
    {
        return match ($this) {
            self::Regular => '100% if not worked',
            self::Special => 'No pay if not worked',
        };
    }
}
