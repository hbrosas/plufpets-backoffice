<?php

namespace App\Http\Requests;

use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use App\Models\HotelStay;
use App\Support\HotelStayRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHotelStayDetailsRequest extends FormRequest
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
        $stay = $this->route('hotel_stay');
        $stayId = $stay instanceof HotelStay ? $stay->id : 0;
        $currentPodTypeId = $stay instanceof HotelStay ? $stay->hotel_pod_type_id : null;

        return [
            'client_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'hotel_pod_type_id' => [
                'required',
                'integer',
                Rule::exists('hotel_pod_types', 'id')->where(function ($query) use ($currentPodTypeId): void {
                    $query->where('is_active', true);

                    if ($currentPodTypeId !== null) {
                        $query->orWhere('id', $currentPodTypeId);
                    }
                }),
            ],
            'pets' => ['required', 'array', 'min:1'],
            'pets.*.id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_stay_pets', 'id')->where('hotel_stay_id', $stayId),
            ],
            'pets.*.name' => ['required', 'string', 'max:255'],
            'pets.*.breed' => ['required', 'string', 'max:255'],
            'pets.*.age' => ['required', 'string', 'max:50'],
            'pets.*.gender' => ['required', Rule::enum(HotelPetGender::class)],
            'pets.*.has_vaccine_booklet' => ['required', 'boolean'],
            'pets.*.vaccines' => ['present', 'array'],
            'pets.*.vaccines.*' => ['string', Rule::enum(HotelVaccine::class)],
            'pets.*.feeding_instructions' => ['nullable', 'string', 'max:1000'],
            'pets.*.medication_instructions' => ['nullable', 'string', 'max:1000'],
            'pets.*.special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...HotelStayRules::messages(),
            'pets.*.id.exists' => 'Choose a pet on this stay.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $pets = $this->input('pets');

                if (! is_array($pets)) {
                    return;
                }

                foreach ($pets as $index => $pet) {
                    if (! is_array($pet) || ! is_array($pet['vaccines'] ?? null)) {
                        continue;
                    }

                    if (count($pet['vaccines']) !== count(array_unique($pet['vaccines']))) {
                        $validator->errors()->add('pets.'.$index.'.vaccines', 'Choose each vaccine once.');
                    }
                }
            },
        ];
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

                $id = $pet['id'] ?? null;

                return [
                    ...$pet,
                    'id' => is_numeric($id) ? (int) $id : null,
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
