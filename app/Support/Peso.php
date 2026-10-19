<?php

namespace App\Support;

class Peso
{
    public static function format(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }
}
