<?php

namespace App\Support;

class SaleReceipt
{
    /**
     * @param  array{
     *     sold_at: string,
     *     number: string,
     *     items: list<array{name: string, quantity: int|string, subtotal: string}>,
     *     total: string,
     *     tendered: string,
     *     change: string,
     *     payments: list<array{name: string, reference?: string|null}>
     * }  $sale
     */
    public static function text(array $sale, int $width = 48): string
    {
        $width = max(24, min(48, $width));
        $line = str_repeat('-', $width);
        $lines = [
            self::wrap($sale['sold_at'].' - '.$sale['number'], $width),
        ];

        foreach ($sale['items'] as $item) {
            $lines[] = self::pair(
                (string) $item['name'],
                trim((string) $item['quantity']).'  '.self::ascii((string) $item['subtotal']),
                $width,
            );
        }

        $lines[] = self::pair('Total', self::ascii($sale['total']), $width);
        $lines[] = self::pair('Tendered Amount', self::ascii($sale['tendered']), $width);
        $lines[] = self::pair('Change', self::ascii($sale['change']), $width);
        $lines[] = $line;

        foreach ($sale['payments'] as $payment) {
            $reference = trim((string) ($payment['reference'] ?? ''));
            $label = $reference === '' ? $payment['name'] : $payment['name'].' - '.$reference;
            $lines[] = self::wrap($label, $width);
        }

        return implode("\n", $lines);
    }

    private static function ascii(string $value): string
    {
        return str_replace('₱', 'P', $value);
    }

    private static function wrap(string $value, int $width): string
    {
        $value = trim(self::ascii($value));

        if ($value === '') {
            return '';
        }

        return wordwrap($value, $width, "\n", true);
    }

    private static function pair(string $left, string $right, int $width): string
    {
        $left = self::ascii($left);
        $right = self::ascii($right);
        $space = $width - mb_strlen($left) - mb_strlen($right);

        if ($space < 1) {
            return self::wrap($left, $width)."\n".str_pad($right, $width, ' ', STR_PAD_LEFT);
        }

        return $left.str_repeat(' ', $space).$right;
    }
}
