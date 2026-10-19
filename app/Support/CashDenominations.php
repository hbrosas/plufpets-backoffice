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
}
