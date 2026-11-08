<?php

namespace App\Http\Requests;

use App\Models\DeliverySchedule;
use App\Models\PosOpenOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignShopOrderDeliveryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('delivery_schedule_id') === '' || $this->input('delivery_schedule_id') === null) {
            $this->merge([
                'delivery_schedule_id' => null,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_schedule_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $schedule = DeliverySchedule::query()->find($value);

                    if (! $schedule instanceof DeliverySchedule || ! $schedule->isAssignable()) {
                        $fail('Choose an active delivery schedule.');

                        return;
                    }

                    $order = $this->route('pos_open_order');

                    if (! $order instanceof PosOpenOrder || $schedule->branch_id !== $order->branch_id) {
                        $fail('Choose an active delivery schedule for this branch.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_schedule_id.integer' => 'Choose an active delivery schedule.',
        ];
    }
}
