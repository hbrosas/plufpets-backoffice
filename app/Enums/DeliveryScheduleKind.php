<?php

namespace App\Enums;

enum DeliveryScheduleKind: string
{
    case Date = 'date';
    case Frequency = 'frequency';

    public function label(): string
    {
        return match ($this) {
            self::Date => 'By date',
            self::Frequency => 'By frequency',
        };
    }
}
