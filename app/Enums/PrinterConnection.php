<?php

namespace App\Enums;

enum PrinterConnection: string
{
    case Browser = 'browser';
    case Bluetooth = 'bluetooth';
    case Serial = 'serial';

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Browser print',
            self::Bluetooth => 'Bluetooth',
            self::Serial => 'USB serial',
        };
    }
}
