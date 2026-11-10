<?php

namespace App\Models;

use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use Database\Factories\HotelStayPetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_stay_id',
    'name',
    'breed',
    'age',
    'gender',
    'has_vaccine_booklet',
    'vaccines',
    'feeding_instructions',
    'medication_instructions',
    'special_instructions',
])]
class HotelStayPet extends Model
{
    /** @use HasFactory<HotelStayPetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => HotelPetGender::class,
            'has_vaccine_booklet' => 'boolean',
            'vaccines' => 'array',
        ];
    }

    /**
     * @return BelongsTo<HotelStay, $this>
     */
    public function stay(): BelongsTo
    {
        return $this->belongsTo(HotelStay::class, 'hotel_stay_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toRegisterPayload(): array
    {
        $vaccines = collect($this->vaccines ?? [])
            ->map(fn (mixed $value): ?string => HotelVaccine::tryFrom((string) $value)?->label())
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'breed' => $this->breed,
            'age' => $this->age,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'has_vaccine_booklet' => $this->has_vaccine_booklet,
            'vaccines' => $vaccines,
            'feeding_instructions' => $this->feeding_instructions,
            'medication_instructions' => $this->medication_instructions,
            'special_instructions' => $this->special_instructions,
        ];
    }
}
