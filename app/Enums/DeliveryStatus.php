<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case ForDelivery = 'for_delivery';
    case PartiallyDelivered = 'partially_delivered';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::ForDelivery => 'For Delivery',
            self::PartiallyDelivered => 'Partially Delivered',
            self::Completed => 'Completed',
        };
    }
}
