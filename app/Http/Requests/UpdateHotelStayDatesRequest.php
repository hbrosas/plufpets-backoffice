<?php

namespace App\Http\Requests;

use App\Models\HotelStay;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelStayDatesRequest extends FormRequest
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
        return [
            'checked_in_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'checkout_on' => ['required', 'date_format:Y-m-d'],
            'checked_out_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'checked_in_at.required' => 'Enter the check-in date.',
            'checked_in_at.date_format' => 'Enter the check-in date.',
            'checkout_on.required' => 'Enter the check-out date.',
            'checkout_on.date_format' => 'Enter the check-out date.',
            'checked_out_at.date_format' => 'Enter the checked-out date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('checked_out_at')) {
            $this->merge(['checked_out_at' => null]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $checkedIn = $this->dateTime('checked_in_at');
                $checkoutOn = CarbonImmutable::createFromFormat('!Y-m-d', $this->string('checkout_on')->toString(), ReportPeriod::ZONE);

                if ($checkedIn === null || $checkoutOn === false) {
                    return;
                }

                if ($checkoutOn->toDateString() < $checkedIn->toDateString()) {
                    $validator->errors()->add('checkout_on', 'Check-out date must be on or after the check-in date.');
                }

                /** @var HotelStay $stay */
                $stay = $this->route('hotel_stay');

                if ($stay->isCheckedIn()) {
                    return;
                }

                $checkedOut = $this->dateTime('checked_out_at');

                if ($checkedOut === null) {
                    $validator->errors()->add('checked_out_at', 'Enter the checked-out date.');

                    return;
                }

                if ($checkedOut->lessThan($checkedIn)) {
                    $validator->errors()->add('checked_out_at', 'Checked-out time must be on or after check-in.');
                }
            },
        ];
    }

    private function dateTime(string $field): ?CarbonImmutable
    {
        $value = $this->input($field);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, ReportPeriod::ZONE);

        return $date === false ? null : $date;
    }
}
