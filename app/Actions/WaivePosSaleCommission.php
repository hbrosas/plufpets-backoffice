<?php

namespace App\Actions;

use App\Models\PosSale;
use App\Models\PosSaleItemCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaivePosSaleCommission
{
    /**
     * @param  array{employee_number: string, name: string}  $actor
     */
    public function handle(PosSale $sale, ?int $employeeId, array $actor): void
    {
        DB::transaction(function () use ($sale, $employeeId, $actor): void {
            $sale = PosSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->refunded_at !== null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This receipt is refunded.',
                ]);
            }

            $commissions = PosSaleItemCommission::query()
                ->whereHas('saleItem', fn ($query) => $query->where('pos_sale_id', $sale->id))
                ->get();

            if ($commissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This receipt has no commissions.',
                ]);
            }

            $targets = $commissions
                ->whereNull('waived_at')
                ->when($employeeId !== null, fn ($rows) => $rows->where('employee_id', $employeeId));

            if ($targets->isEmpty()) {
                throw ValidationException::withMessages([
                    'employee_id' => $employeeId === null
                        ? 'Every commission on this receipt is already waived.'
                        : 'Choose an employee who still has a commission on this receipt.',
                ]);
            }

            PosSaleItemCommission::query()->whereKey($targets->modelKeys())->update([
                'waived_at' => now(),
                'waived_by_employee_number' => $actor['employee_number'],
                'waived_by_name' => $actor['name'],
            ]);
        });
    }
}
