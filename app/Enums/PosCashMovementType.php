<?php

namespace App\Enums;

enum PosCashMovementType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
    case OpenDrawer = 'open_drawer';

    public function label(): string
    {
        return match ($this) {
            self::CashIn => 'Cash in',
            self::CashOut => 'Cash out',
            self::OpenDrawer => 'Open drawer',
        };
    }
}
