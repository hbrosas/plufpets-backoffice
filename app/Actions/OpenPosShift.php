<?php

namespace App\Actions;

use App\Enums\PosDenominationCountType;
use App\Enums\PosShiftStatus;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosShiftDenomination;
use App\Support\CashDenominations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenPosShift
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  list<array{denomination: mixed, quantity: mixed}>  $denominations
     */
    public function handle(Branch $branch, array $denominations, array $employee): PosShift
    {
        return DB::transaction(function () use ($branch, $denominations, $employee): PosShift {
            $openShift = PosShift::query()
                ->where('branch_id', $branch->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($openShift !== null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'This branch already has an open shift.',
                ]);
            }

            if (! $branch->is_active) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Choose an active branch.',
                ]);
            }

            $shift = PosShift::query()->create([
                'branch_id' => $branch->id,
                'status' => PosShiftStatus::Open,
                'opened_by_employee_number' => $employee['employee_number'],
                'opened_by_name' => $employee['name'],
                'opened_at' => now(),
                'starting_cash' => CashDenominations::total($denominations),
            ]);

            foreach ($denominations as $row) {
                PosShiftDenomination::query()->create([
                    'pos_shift_id' => $shift->id,
                    'count_type' => PosDenominationCountType::Opening,
                    'denomination' => $row['denomination'],
                    'quantity' => $row['quantity'],
                ]);
            }

            return $shift;
        });
    }
}
