<?php

namespace App\Http\Requests;

use App\Support\HotelStayRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHotelStayRequest extends FormRequest
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
        return HotelStayRules::checkIn();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return HotelStayRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $pets = $this->input('pets');

        if (! is_array($pets)) {
            return;
        }

        $this->merge([
            'client_name' => trim((string) $this->input('client_name')),
            'contact_number' => trim((string) $this->input('contact_number')),
            'address' => trim((string) $this->input('address')),
            'pets' => array_map(function (mixed $pet): mixed {
                if (! is_array($pet)) {
                    return $pet;
                }

                return [
                    ...$pet,
                    'name' => trim((string) ($pet['name'] ?? '')),
                    'breed' => trim((string) ($pet['breed'] ?? '')),
                    'age' => trim((string) ($pet['age'] ?? '')),
                    'has_vaccine_booklet' => filter_var($pet['has_vaccine_booklet'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'vaccines' => array_values(array_filter(
                        is_array($pet['vaccines'] ?? null) ? $pet['vaccines'] : [],
                        fn (mixed $vaccine): bool => is_string($vaccine) && $vaccine !== '',
                    )),
                    'feeding_instructions' => $this->blank($pet['feeding_instructions'] ?? null),
                    'medication_instructions' => $this->blank($pet['medication_instructions'] ?? null),
                    'special_instructions' => $this->blank($pet['special_instructions'] ?? null),
                ];
            }, $pets),
        ]);
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
