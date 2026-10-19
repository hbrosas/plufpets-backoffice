<?php

namespace App\Actions;

use App\Enums\PosCashMovementType;
use App\Models\PosCashMovement;
use App\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPosCashMovement
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     */
    public function handle(PosShift $shift, PosCashMovementType $type, string $amount, string $reason, array $employee): PosCashMovement
    {
        return DB::transaction(function () use ($shift, $type, $amount, $reason, $employee): PosCashMovement {
            $shift = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Open a shift before recording cash in or cash out.',
                ]);
            }

            return PosCashMovement::query()->create([
                'pos_shift_id' => $shift->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'employee_number' => $employee['employee_number'],
                'employee_name' => $employee['name'],
                'recorded_at' => now(),
            ]);
        });
    }
}
