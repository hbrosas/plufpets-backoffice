<?php

namespace App\Support;

class MobileNumber
{
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    public static function pattern(): string
    {
        return '/^09\d{9}$/';
    }
}
