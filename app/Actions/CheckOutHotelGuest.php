<?php

namespace App\Actions;

use App\Models\HotelStay;
use App\Models\PosOpenOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutHotelGuest
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     */
    public function handle(HotelStay $stay, array $employee): HotelStay
    {
        return DB::transaction(function () use ($stay, $employee): HotelStay {
            $stay = HotelStay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();

            if (! $stay->isCheckedIn()) {
                throw ValidationException::withMessages([
                    'status' => 'This guest is already checked out.',
                ]);
            }

            $stay->update([
                'checked_out_at' => now(),
                'checked_out_by_employee_number' => $employee['employee_number'],
                'checked_out_by_name' => $employee['name'],
            ]);

            PosOpenOrder::query()
                ->where('hotel_stay_id', $stay->id)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', ''))
                ->delete();

            return $stay;
        });
    }
}
