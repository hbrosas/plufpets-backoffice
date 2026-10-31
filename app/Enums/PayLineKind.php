<?php

namespace App\Enums;

enum PayLineKind: string
{
    case Compensation = 'compensation';
    case Benefit = 'benefit';
    case Government = 'government';
}
