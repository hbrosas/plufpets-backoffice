<?php

namespace App\Http\Requests;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use App\Models\DeliverySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDeliveryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('kind') !== DeliveryScheduleKind::Date->value) {
            $this->merge(['scheduled_on' => null]);
        }

        if ($this->input('kind') !== DeliveryScheduleKind::Frequency->value) {
            $this->merge([
                'frequency' => null,
                'weekdays' => null,
            ]);
        }

        if ($this->input('frequency') !== DeliveryScheduleFrequency::Weekly->value) {
            $this->merge(['weekdays' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'kind' => ['required', Rule::enum(DeliveryScheduleKind::class)],
            'scheduled_on' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('kind') === DeliveryScheduleKind::Date->value),
            ],
            'frequency' => [
                'nullable',
                Rule::enum(DeliveryScheduleFrequency::class),
                Rule::requiredIf(fn (): bool => $this->input('kind') === DeliveryScheduleKind::Frequency->value),
            ],
            'weekdays' => [
                'nullable',
                'array',
                'min:1',
                Rule::requiredIf(fn (): bool => $this->input('frequency') === DeliveryScheduleFrequency::Weekly->value),
            ],
            'weekdays.*' => ['integer', 'distinct', Rule::in([1, 2, 3, 4, 5, 6, 7])],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Choose a branch.',
            'branch_id.exists' => 'Choose a branch.',
            'kind.required' => 'Choose by date or by frequency.',
            'kind.enum' => 'Choose by date or by frequency.',
            'scheduled_on.required' => 'Enter the delivery date.',
            'scheduled_on.date' => 'Enter the delivery date.',
            'frequency.required' => 'Choose how often this schedule repeats.',
            'frequency.enum' => 'Choose how often this schedule repeats.',
            'weekdays.required' => 'Choose at least one day.',
            'weekdays.min' => 'Choose at least one day.',
            'slots.required' => 'Add a time slot.',
            'slots.min' => 'Add a time slot.',
            'slots.*.time.required' => 'Enter a time.',
            'slots.*.time.date_format' => 'Enter a time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $kind = DeliveryScheduleKind::from($this->string('kind')->toString());
            $date = $kind === DeliveryScheduleKind::Date ? $this->string('scheduled_on')->toString() : null;
            $frequency = $kind === DeliveryScheduleKind::Frequency
                ? DeliveryScheduleFrequency::from($this->string('frequency')->toString())
                : null;
            $weekdays = $frequency === DeliveryScheduleFrequency::Weekly
                ? collect($this->input('weekdays'))->map(fn (mixed $day): int => (int) $day)->sort()->values()->all()
                : null;
            $seen = [];

            foreach ($this->input('slots') as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $time = (string) ($slot['time'] ?? '');

                if (isset($seen[$time])) {
                    $validator->errors()->add("slots.$index.time", 'That time slot is already in this schedule.');

                    continue;
                }

                $seen[$time] = true;

                if ($date !== null && CarbonImmutable::parse($date.' '.$time, 'Asia/Manila')->lessThanOrEqualTo(CarbonImmutable::now('Asia/Manila'))) {
                    $validator->errors()->add("slots.$index.time", 'Choose a time that has not passed.');

                    continue;
                }

                $exists = DeliverySchedule::query()
                    ->where('branch_id', $this->integer('branch_id'))
                    ->where('kind', $kind)
                    ->where('starts_at', $time.':00')
                    ->get()
                    ->contains(fn (DeliverySchedule $schedule): bool => $schedule->sameOccurrence($date, $frequency, $weekdays));

                if ($exists) {
                    $validator->errors()->add("slots.$index.time", 'That time slot already exists for this branch.');
                }
            }
        });
    }
}
