<?php

namespace App\Actions;

use App\Enums\CommissionRole;
use App\Models\Employee;

class CalculateServiceCommission
{
    /**
     * @param  list<array{employee: Employee, rate: float}>  $people
     * @return list<array{employee_id: int, employee_number: string, employee_name: string, role: string, rate: string, pool_size: int, commissionable_amount: string, amount: string}>
     */
    public function forPool(float $commissionable, CommissionRole $role, array $people): array
    {
        $poolSize = count($people);
        $commissionableAmount = number_format($commissionable, 2, '.', '');

        return array_map(function (array $person) use ($commissionable, $role, $poolSize, $commissionableAmount): array {
            $rate = number_format($person['rate'], 2, '.', '');

            return [
                'employee_id' => $person['employee']->id,
                'employee_number' => $person['employee']->employee_number,
                'employee_name' => $person['employee']->name,
                'role' => $role->value,
                'rate' => $rate,
                'pool_size' => $poolSize,
                'commissionable_amount' => $commissionableAmount,
                'amount' => $this->share($commissionable, (float) $rate, $poolSize),
            ];
        }, $people);
    }

    public function share(float $commissionable, float $ratePercent, int $poolSize): string
    {
        if ($poolSize < 1) {
            return '0.00';
        }

        return number_format(round($commissionable * ($ratePercent / 100) / $poolSize, 2), 2, '.', '');
    }
}
