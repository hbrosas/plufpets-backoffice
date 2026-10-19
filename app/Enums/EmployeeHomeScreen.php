<?php

namespace App\Enums;

enum EmployeeHomeScreen: string
{
    case Dashboard = 'dashboard';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard first',
            self::Pos => 'POS first',
        };
    }
}
