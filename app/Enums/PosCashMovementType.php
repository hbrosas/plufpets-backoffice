<?php

namespace App\Enums;

enum PosCashMovementType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';

    public function label(): string
    {
        return match ($this) {
            self::CashIn => 'Cash in',
            self::CashOut => 'Cash out',
        };
    }
}
