<?php

namespace App\Support;

class CashDenominations
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return ['1000.00', '500.00', '200.00', '100.00', '50.00', '20.00', '10.00', '5.00', '1.00'];
    }

    /**
     * @param  list<array{denomination: mixed, quantity: mixed}>  $rows
     */
    public static function total(array $rows): string
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += ((float) $row['denomination']) * ((int) $row['quantity']);
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @param  iterable<int, object{denomination: mixed, quantity: mixed}>  $rows
     * @return list<array{denomination: string, quantity: int, total: string}>
     */
    public static function breakdown(iterable $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            $quantity = (int) $row->quantity;

            if ($quantity < 1) {
                continue;
            }

            $denomination = (float) $row->denomination;
            $lines[] = [
                'sort' => $denomination,
                'denomination' => Peso::format($denomination),
                'quantity' => $quantity,
                'total' => Peso::format($denomination * $quantity),
            ];
        }

        usort($lines, fn (array $left, array $right): int => $right['sort'] <=> $left['sort']);

        return array_map(function (array $line): array {
            unset($line['sort']);

            return $line;
        }, $lines);
    }
}
