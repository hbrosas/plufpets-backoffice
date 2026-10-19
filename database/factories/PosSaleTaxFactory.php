<?php

namespace Database\Factories;

use App\Enums\TaxFeeType;
use App\Models\PosSale;
use App\Models\PosSaleTax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSaleTax>
 */
class PosSaleTaxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_id' => PosSale::factory(),
            'name' => 'VAT',
            'type' => TaxFeeType::Percent,
            'rate' => 12,
            'amount' => 12,
        ];
    }
}
