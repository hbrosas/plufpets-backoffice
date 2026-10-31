<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Probationary = 'probationary';
    case Regular = 'regular';
    case Contractual = 'contractual';
    case PartTime = 'part_time';

    public function label(): string
    {
        return match ($this) {
            self::Probationary => 'Probationary',
            self::Regular => 'Regular',
            self::Contractual => 'Contractual',
            self::PartTime => 'Part-time',
        };
    }
}
