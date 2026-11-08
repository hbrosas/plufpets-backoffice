<?php

namespace App\Http\Requests;

use App\Models\DeliverySchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryScheduleRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => 'Choose whether the delivery schedule is active.',
            'is_active.boolean' => 'Choose whether the delivery schedule is active.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active')) {
                return;
            }

            $schedule = $this->route('delivery_schedule');

            if ($schedule instanceof DeliverySchedule && $schedule->hasEnded()) {
                $validator->errors()->add('is_active', 'That delivery schedule has ended.');
            }
        });
    }
}
