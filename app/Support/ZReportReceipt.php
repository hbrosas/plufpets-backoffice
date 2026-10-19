<?php

namespace App\Support;

class ZReportReceipt
{
    /**
     * @param  array{
     *     app: string,
     *     branch: string,
     *     opened: string,
     *     closed: string,
     *     payments: list<array{name: string, count: int|string, total: string}>,
     *     item_count: int|string,
     *     sales_total: string,
     *     starting_cash: string,
     *     cash_sales: string,
     *     cash_in: string,
     *     cash_out: string,
     *     expected_cash: string,
     *     counted_cash: string,
     *     variance: string,
     *     remit_amount: string
     * }  $report
     */
    public static function text(array $report, int $width = 48): string
    {
        $width = max(24, min(48, $width));
        $line = str_repeat('-', $width);
        $lines = [
            self::center($report['app'], $width),
            self::center('Z-REPORT', $width),
            self::center($report['branch'], $width),
            $line,
            self::wrap($report['opened'], $width),
            self::wrap($report['closed'], $width),
            $line,
            self::pair('Payment', 'Amount', $width),
        ];

        if ($report['payments'] === []) {
            $lines[] = self::wrap('No sales this shift.', $width);
        } else {
            foreach ($report['payments'] as $payment) {
                $lines[] = self::pair(
                    $payment['name'].' ('.$payment['count'].')',
                    self::ascii((string) $payment['total']),
                    $width,
                );
            }
        }

        $lines[] = $line;
        $lines[] = self::pair('Items sold', (string) $report['item_count'], $width);
        $lines[] = self::pair('Sales total', self::ascii($report['sales_total']), $width);
        $lines[] = self::pair('Starting cash', self::ascii($report['starting_cash']), $width);
        $lines[] = self::pair('Cash sales', self::ascii($report['cash_sales']), $width);
        $lines[] = self::pair('Cash in', self::ascii($report['cash_in']), $width);
        $lines[] = self::pair('Cash out', self::ascii($report['cash_out']), $width);
        $lines[] = self::pair('Expected cash', self::ascii($report['expected_cash']), $width);
        $lines[] = self::pair('Counted cash', self::ascii($report['counted_cash']), $width);
        $lines[] = self::pair('Variance', self::ascii($report['variance']), $width);
        $lines[] = self::pair('Amount to remit', self::ascii($report['remit_amount']), $width);
        $lines[] = $line;

        return implode("\n", $lines)."\n";
    }

    private static function ascii(string $value): string
    {
        return str_replace('₱', 'P', $value);
    }

    private static function center(string $value, int $width): string
    {
        $value = trim(self::ascii($value));

        if (mb_strlen($value) >= $width) {
            return mb_substr($value, 0, $width);
        }

        $pad = (int) floor(($width - mb_strlen($value)) / 2);

        return str_repeat(' ', $pad).$value;
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
