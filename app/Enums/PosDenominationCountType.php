<?php

namespace App\Enums;

enum PosDenominationCountType: string
{
    case Opening = 'opening';
    case Closing = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening',
            self::Closing => 'Closing',
        };
    }
}
