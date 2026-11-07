<?php

namespace App\Enums;

enum InventoryHold: string
{
    case Held = 'held';
    case Sold = 'sold';
    case Released = 'released';
}
