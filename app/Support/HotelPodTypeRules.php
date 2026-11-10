<?php

namespace App\Support;

use App\Models\HotelPodType;
use Illuminate\Validation\Rule;

class HotelPodTypeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?HotelPodType $hotelPodType = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('hotel_pod_types', 'name')->ignore($hotelPodType?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a pod type name.',
            'name.unique' => 'That pod type already exists.',
        ];
    }
}
