<?php

namespace Database\Factories;

use App\Enums\PosDenominationCountType;
use App\Models\PosShift;
use App\Models\PosShiftDenomination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShiftDenomination>
 */
class PosShiftDenominationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_shift_id' => PosShift::factory(),
            'count_type' => PosDenominationCountType::Opening,
            'denomination' => 1000,
            'quantity' => 1,
        ];
    }
}
