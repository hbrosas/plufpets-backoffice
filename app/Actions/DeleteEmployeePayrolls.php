<?php

namespace App\Actions;

use App\Models\EmployeePayroll;
use App\Models\PayrollFiling;
use Illuminate\Support\Facades\DB;

class DeleteEmployeePayrolls
{
    /**
     * @param  array<int, int>  $ids
     */
    public function handle(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            PayrollFiling::query()
                ->whereIn('deducted_payroll_id', $ids)
                ->update(['deducted_payroll_id' => null]);

            EmployeePayroll::query()->whereIn('id', $ids)->delete();
        });
    }
}
