<?php

namespace Database\Factories;

use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use App\Models\HotelStay;
use App\Models\HotelStayPet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelStayPet>
 */
class HotelStayPetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_stay_id' => HotelStay::factory(),
            'name' => fake()->firstName(),
            'breed' => 'Aspin',
            'age' => '2 years',
            'gender' => HotelPetGender::Male,
            'has_vaccine_booklet' => true,
            'vaccines' => [HotelVaccine::AntiRabies->value],
            'feeding_instructions' => null,
            'medication_instructions' => null,
            'special_instructions' => null,
        ];
    }
}
