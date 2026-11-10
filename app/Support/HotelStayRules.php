<?php

namespace App\Support;

use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use Illuminate\Validation\Rule;

class HotelStayRules
{
    /**
     * @return array<string, mixed>
     */
    public static function checkIn(): array
    {
        $today = now(ReportPeriod::ZONE)->toDateString();

        return [
            'client_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'checkout_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'hotel_pod_type_id' => ['required', 'integer', Rule::exists('hotel_pod_types', 'id')->where('is_active', true)],
            'pets' => ['required', 'array', 'min:1'],
            'pets.*.name' => ['required', 'string', 'max:255'],
            'pets.*.breed' => ['required', 'string', 'max:255'],
            'pets.*.age' => ['required', 'string', 'max:50'],
            'pets.*.gender' => ['required', Rule::enum(HotelPetGender::class)],
            'pets.*.has_vaccine_booklet' => ['required', 'boolean'],
            'pets.*.vaccines' => ['present', 'array'],
            'pets.*.vaccines.*' => ['string', 'distinct', Rule::enum(HotelVaccine::class)],
            'pets.*.feeding_instructions' => ['nullable', 'string', 'max:1000'],
            'pets.*.medication_instructions' => ['nullable', 'string', 'max:1000'],
            'pets.*.special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payment(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:9999999.99'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'client_name.required' => 'Enter the client name.',
            'contact_number.required' => 'Enter the contact number.',
            'address.required' => 'Enter the address.',
            'checkout_on.required' => 'Enter the check-out date.',
            'checkout_on.date_format' => 'Enter the check-out date.',
            'checkout_on.after_or_equal' => 'Check-out date must be today or later.',
            'hotel_pod_type_id.required' => 'Choose a pod type.',
            'hotel_pod_type_id.integer' => 'Choose a pod type.',
            'hotel_pod_type_id.exists' => 'Choose a pod type.',
            'pets.required' => 'Add at least one pet.',
            'pets.min' => 'Add at least one pet.',
            'pets.*.name.required' => 'Enter the pet name.',
            'pets.*.breed.required' => 'Enter the breed.',
            'pets.*.age.required' => 'Enter the age.',
            'pets.*.gender.required' => 'Choose male or female.',
            'pets.*.gender.enum' => 'Choose male or female.',
            'pets.*.has_vaccine_booklet.required' => 'Say whether the pet has a vaccine booklet.',
            'pets.*.vaccines.*.enum' => 'Choose a vaccine from the list.',
            'pets.*.vaccines.*.distinct' => 'Choose each vaccine once.',
            'payment_method_id.required' => 'Choose a payment method.',
            'payment_method_id.exists' => 'Choose an active payment method.',
            'amount.required' => 'Enter a payment amount.',
            'amount.min' => 'Enter a payment amount.',
        ];
    }
}
