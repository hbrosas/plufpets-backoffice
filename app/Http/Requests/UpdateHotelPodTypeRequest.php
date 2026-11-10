<?php

namespace App\Http\Requests;

use App\Models\HotelPodType;
use App\Support\HotelPodTypeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelPodTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hotelPodType = $this->route('hotel_pod_type');

        return HotelPodTypeRules::for($hotelPodType instanceof HotelPodType ? $hotelPodType : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return HotelPodTypeRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
