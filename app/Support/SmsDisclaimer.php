<?php

namespace App\Support;

class SmsDisclaimer
{
    public const TEXT = 'Do not reply to this message.';

    public static function append(string $message): string
    {
        $message = rtrim($message);

        if (str_ends_with($message, self::TEXT)) {
            return $message;
        }

        return $message."\n\n".self::TEXT;
    }

    public static function bodyMax(): int
    {
        return 1600 - strlen("\n\n".self::TEXT);
    }
}
