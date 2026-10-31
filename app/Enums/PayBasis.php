<?php

namespace App\Enums;

enum PayBasis: string
{
    case Day = 'day';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Per day',
            self::Month => 'Per month',
        };
    }
}
