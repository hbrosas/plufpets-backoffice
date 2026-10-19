<?php

namespace App\Enums;

enum PrinterRole: string
{
    case Receipt = 'receipt';
    case Label = 'label';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipts',
            self::Label => 'Labels',
        };
    }
}
