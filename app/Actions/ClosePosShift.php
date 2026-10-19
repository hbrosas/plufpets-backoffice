<?php

namespace App\Actions;

use App\Enums\PosDenominationCountType;
use App\Enums\PosShiftStatus;
use App\Models\PosShift;
use App\Models\PosShiftDenomination;
use App\Support\CashDenominations;
use App\Support\Peso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosePosShift
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  list<array{denomination: mixed, quantity: mixed}>  $denominations
     */
    public function handle(PosShift $shift, array $denominations, string $remitAmount, bool $confirmVariance, array $employee): PosShift
    {
        return DB::transaction(function () use ($shift, $denominations, $remitAmount, $confirmVariance, $employee): PosShift {
            $shift = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'This shift is already closed.',
                ]);
            }

            $counted = CashDenominations::total($denominations);
            $expected = $shift->expectedDrawerCash();
            $variance = number_format(((float) $counted) - ((float) $expected), 2, '.', '');
            $countIsComplete = abs((float) $variance) < 0.005;

            if (! $countIsComplete && ! $confirmVariance) {
                throw ValidationException::withMessages([
                    'counted_cash' => 'Cash count is not complete. Expected '.Peso::format($expected).', counted '.Peso::format($counted).'. Confirm the variance to close.',
                ]);
            }

            if ((float) $remitAmount - (float) $counted > 0.005) {
                throw ValidationException::withMessages([
                    'remit_amount' => 'The remit amount cannot be more than the counted cash.',
                ]);
            }

            foreach ($denominations as $row) {
                PosShiftDenomination::query()->create([
                    'pos_shift_id' => $shift->id,
                    'count_type' => PosDenominationCountType::Closing,
                    'denomination' => $row['denomination'],
                    'quantity' => $row['quantity'],
                ]);
            }

            $shift->update([
                'status' => PosShiftStatus::Closed,
                'closed_by_employee_number' => $employee['employee_number'],
                'closed_by_name' => $employee['name'],
                'closed_at' => now(),
                'counted_cash' => $counted,
                'expected_cash' => $expected,
                'variance' => $variance,
                'remit_amount' => $remitAmount,
            ]);

            return $shift->refresh();
        });
    }
}
