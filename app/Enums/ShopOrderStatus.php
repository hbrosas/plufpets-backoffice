<?php

namespace App\Enums;

enum ShopOrderStatus: string
{
    case Placed = 'placed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Order Placed',
            self::Preparing => 'Preparing Order',
            self::OutForDelivery => 'Out for Delivery',
            self::Completed => 'Completed',
        };
    }

    public function templateKey(): string
    {
        return 'shop.'.$this->value;
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::Placed => 'Hi {name}, we received order {order} for {total} at {branch}.',
            self::Preparing => 'Hi {name}, we are preparing order {order}.',
            self::OutForDelivery => 'Hi {name}, order {order} is out for delivery.',
            self::Completed => 'Hi {name}, order {order} is complete. Thank you.',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
