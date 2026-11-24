<?php

namespace App\Actions;

use App\Enums\HotelPetGender;
use App\Models\HotelStay;
use App\Models\HotelStayPet;
use Illuminate\Support\Facades\DB;

class UpdateHotelStayDetails
{
    /**
     * @param  array{
     *     client_name: string,
     *     contact_number: string,
     *     address: string,
     *     hotel_pod_type_id: int,
     *     pets: list<array<string, mixed>>
     * }  $attributes
     */
    public function handle(HotelStay $stay, array $attributes): HotelStay
    {
        return DB::transaction(function () use ($stay, $attributes): HotelStay {
            $stay->update([
                'client_name' => $attributes['client_name'],
                'contact_number' => $attributes['contact_number'],
                'address' => $attributes['address'],
                'hotel_pod_type_id' => $attributes['hotel_pod_type_id'],
            ]);

            $keptIds = [];

            foreach ($attributes['pets'] as $pet) {
                $values = [
                    'name' => $pet['name'],
                    'breed' => $pet['breed'],
                    'age' => $pet['age'],
                    'gender' => HotelPetGender::from($pet['gender']),
                    'has_vaccine_booklet' => (bool) $pet['has_vaccine_booklet'],
                    'vaccines' => array_values($pet['vaccines'] ?? []),
                    'feeding_instructions' => $this->blankToNull($pet['feeding_instructions'] ?? null),
                    'medication_instructions' => $this->blankToNull($pet['medication_instructions'] ?? null),
                    'special_instructions' => $this->blankToNull($pet['special_instructions'] ?? null),
                ];

                $existing = isset($pet['id'])
                    ? $stay->pets()->whereKey($pet['id'])->first()
                    : null;

                if ($existing instanceof HotelStayPet) {
                    $existing->update($values);
                    $keptIds[] = $existing->id;

                    continue;
                }

                $keptIds[] = $stay->pets()->create($values)->id;
            }

            $stay->pets()->whereKeyNot($keptIds)->delete();

            return $stay->load('pets');
        });
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
