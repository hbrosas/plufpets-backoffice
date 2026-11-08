<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\DeliveryMethod;
use App\Models\DeliverySchedule;
use App\Models\PaymentMethod;
use App\Support\MobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopOrderRequest extends FormRequest
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
            'branch_id' => [
                'required',
                'integer',
                Rule::exists(Branch::class, 'id')->where('is_active', true),
            ],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:'.MobileNumber::pattern()],
            'delivery_method_id' => [
                'required',
                'integer',
                Rule::exists(DeliveryMethod::class, 'id')->where('is_active', true),
            ],
            'delivery_schedule_id' => ['nullable', 'integer'],
            'preferred_on' => ['nullable', 'date_format:Y-m-d'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists(PaymentMethod::class, 'id')->where('is_active', true),
            ],
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'address_line_1' => ['nullable', 'string', 'max:160', Rule::requiredIf(fn (): bool => $this->asksForAddress())],
            'address_line_2' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $this->asksForAddress())],
            'province' => ['nullable', 'string', 'max:80', Rule::requiredIf(fn (): bool => $this->asksForAddress())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.catalog_item_variant_id' => ['required', 'integer', 'distinct', 'exists:catalog_item_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
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
            'name.required' => 'Enter your name.',
            'phone.required' => 'Enter your contact number.',
            'phone.regex' => 'Enter a mobile number like 09171234567.',
            'delivery_method_id.required' => 'Choose a delivery method.',
            'delivery_method_id.exists' => 'Choose a delivery method.',
            'delivery_schedule_id.integer' => 'Choose a preferred slot.',
            'preferred_on.date_format' => 'Choose a preferred slot.',
            'payment_method_id.required' => 'Choose a payment method.',
            'payment_method_id.exists' => 'Choose a payment method.',
            'address_line_1.required' => 'Enter the delivery address.',
            'city.required' => 'Enter the city.',
            'province.required' => 'Enter the province.',
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.catalog_item_variant_id.distinct' => 'Choose each product once.',
            'items.*.quantity.min' => 'Enter a quantity of at least 1.',
            'voucher_code.max' => 'That voucher is not valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $voucher = strtoupper(trim((string) $this->input('voucher_code')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => MobileNumber::normalize((string) $this->input('phone')),
            'voucher_code' => $voucher === '' ? null : $voucher,
            'address_line_1' => $this->nullableString('address_line_1'),
            'address_line_2' => $this->nullableString('address_line_2'),
            'city' => $this->nullableString('city'),
            'province' => $this->nullableString('province'),
            'latitude' => $this->nullableString('latitude'),
            'longitude' => $this->nullableString('longitude'),
            'delivery_schedule_id' => $this->filled('delivery_schedule_id') ? $this->input('delivery_schedule_id') : null,
            'preferred_on' => $this->filled('preferred_on') ? $this->input('preferred_on') : null,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $delivery = DeliveryMethod::query()->find($this->integer('delivery_method_id'));

                if (! $delivery instanceof DeliveryMethod || ! $delivery->uses_delivery_schedule) {
                    return;
                }

                $schedule = DeliverySchedule::query()->find($this->input('delivery_schedule_id'));
                $preferredOn = $this->input('preferred_on');

                if (! $schedule instanceof DeliverySchedule || ! $schedule->isPreferredSlotFor($this->integer('branch_id')) || ! is_string($preferredOn) || ! $schedule->includesDate($preferredOn)) {
                    $validator->errors()->add('delivery_schedule_id', 'Choose a preferred slot.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customer(): array
    {
        return $this->safe()->only([
            'name',
            'phone',
            'delivery_method_id',
            'delivery_schedule_id',
            'preferred_on',
            'payment_method_id',
            'voucher_code',
            'address_line_1',
            'address_line_2',
            'city',
            'province',
            'latitude',
            'longitude',
        ]);
    }

    private function asksForAddress(): bool
    {
        $delivery = DeliveryMethod::query()->find($this->integer('delivery_method_id'));

        return $delivery instanceof DeliveryMethod && $delivery->asks_for_address;
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
