<?php

namespace App\Actions;

use App\Models\HotelStay;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateHotelStayDates
{
    /**
     * @param  array{checked_in_at: string, checkout_on: string, checked_out_at: string|null}  $dates
     */
    public function handle(HotelStay $stay, array $dates): HotelStay
    {
        return DB::transaction(function () use ($stay, $dates): HotelStay {
            $stay = HotelStay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();
            $checkedIn = $this->dateTime($dates['checked_in_at'], 'checked_in_at');

            $attributes = [
                'checked_in_at' => $checkedIn->utc(),
                'checkout_on' => $dates['checkout_on'],
            ];

            if (! $stay->isCheckedIn()) {
                $attributes['checked_out_at'] = $this->dateTime((string) $dates['checked_out_at'], 'checked_out_at')->utc();
            }

            $stay->update($attributes);

            return $stay;
        });
    }

    private function dateTime(string $value, string $field): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, ReportPeriod::ZONE);

        if ($date === false) {
            throw ValidationException::withMessages([
                $field => $field === 'checked_out_at' ? 'Enter the checked-out date.' : 'Enter the check-in date.',
            ]);
        }

        return $date;
    }
}
