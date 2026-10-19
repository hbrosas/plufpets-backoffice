<?php

namespace App\Enums;

enum CatalogItemType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
        };
    }
}
