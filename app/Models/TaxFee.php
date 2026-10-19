<?php

namespace App\Models;

use App\Enums\TaxFeeType;
use Database\Factories\TaxFeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'rate', 'is_active'])]
class TaxFee extends Model
{
    /** @use HasFactory<TaxFeeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaxFeeType::class,
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
