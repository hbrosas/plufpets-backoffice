<?php

namespace App\Actions;

use App\Enums\HotelPetGender;
use App\Models\HotelStay;
use App\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInHotelGuest
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  array{
     *     client_name: string,
     *     contact_number: string,
     *     address: string,
     *     checkout_on: string,
     *     hotel_pod_type_id: int,
     *     pets: list<array<string, mixed>>
     * }  $attributes
     */
    public function handle(PosShift $shift, array $attributes, array $employee): HotelStay
    {
        return DB::transaction(function () use ($shift, $attributes, $employee): HotelStay {
            $shift = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Open a shift before checking a guest in.',
                ]);
            }

            $stay = HotelStay::query()->create([
                'branch_id' => $shift->branch_id,
                'pos_shift_id' => $shift->id,
                'client_name' => $attributes['client_name'],
                'contact_number' => $attributes['contact_number'],
                'address' => $attributes['address'],
                'checked_in_at' => now(),
                'checkout_on' => $attributes['checkout_on'],
                'hotel_pod_type_id' => $attributes['hotel_pod_type_id'],
                'checked_in_by_employee_number' => $employee['employee_number'],
                'checked_in_by_name' => $employee['name'],
            ]);

            foreach ($attributes['pets'] as $pet) {
                $stay->pets()->create([
                    'name' => $pet['name'],
                    'breed' => $pet['breed'],
                    'age' => $pet['age'],
                    'gender' => HotelPetGender::from($pet['gender']),
                    'has_vaccine_booklet' => (bool) $pet['has_vaccine_booklet'],
                    'vaccines' => array_values($pet['vaccines'] ?? []),
                    'feeding_instructions' => $this->blankToNull($pet['feeding_instructions'] ?? null),
                    'medication_instructions' => $this->blankToNull($pet['medication_instructions'] ?? null),
                    'special_instructions' => $this->blankToNull($pet['special_instructions'] ?? null),
                ]);
            }

            return $stay->load('pets');
        });
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
