<?php

namespace App\Enums;

enum HotelVaccine: string
{
    case AntiRabies = 'anti_rabies';
    case FiveInOne = 'five_in_one';
    case FourInOne = 'four_in_one';
    case KennelCough = 'kennel_cough';
    case Deworming = 'deworming';

    public function label(): string
    {
        return match ($this) {
            self::AntiRabies => 'Anti-Rabies',
            self::FiveInOne => '5-in-1',
            self::FourInOne => '4-in-1',
            self::KennelCough => 'Kennel Cough',
            self::Deworming => 'Deworming',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $vaccine): array => ['value' => $vaccine->value, 'label' => $vaccine->label()],
            self::cases(),
        );
    }
}
