<?php

namespace App\Enums;

enum PayrollFilingType: string
{
    case Leave = 'leave';
    case Overtime = 'overtime';
    case Overnight = 'overnight';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Leave',
            self::Overtime => 'Overtime',
            self::Overnight => 'Overnight',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
