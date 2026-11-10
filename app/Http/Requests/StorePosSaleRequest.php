<?php

namespace App\Http\Requests;

use App\Enums\ShopOrderStatus;
use App\Models\Branch;
use App\Models\DeliveryMethod;
use App\Models\DeliverySchedule;
use App\Models\PosOpenOrder;
use App\Support\PosSaleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosSaleRequest extends FormRequest
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
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : 0;

        return [
            ...PosSaleRules::for(),
            'hotel_stay_id' => [
                'nullable',
                'integer',
                Rule::exists('hotel_stays', 'id')->where(
                    fn ($query) => $query->where('branch_id', $branchId)->whereNull('checked_out_at'),
                ),
            ],
            'apply_hotel_payment' => ['sometimes', 'boolean'],
            'pos_open_order_id' => [
                'nullable',
                'integer',
                Rule::exists('pos_open_orders', 'id')->where(fn ($query) => $query->where('branch_id', $branchId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...PosSaleRules::messages(),
            'hotel_stay_id.exists' => 'Choose a guest who is still checked in.',
            'apply_hotel_payment.boolean' => 'Choose whether to apply the hotel payment.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('pos_open_order_id') || ! $this->filled('pos_open_order_id')) {
                    return;
                }

                $order = PosOpenOrder::query()->find($this->integer('pos_open_order_id'));

                if (! $order instanceof PosOpenOrder || ! is_string($order->status) || $order->status === '') {
                    return;
                }

                if ($order->hasPreorder()) {
                    $validator->errors()->add('pos_open_order_id', 'A pre-order cannot be turned into a sale.');

                    return;
                }

                $canCharge = in_array($order->status, [ShopOrderStatus::Placed->value, ShopOrderStatus::Preparing->value], true);

                if ($order->pos_sale_id !== null || ! $canCharge) {
                    $validator->errors()->add('pos_open_order_id', 'Create a sale from an order that is still open.');
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('delivery_method_id')) {
                    return;
                }

                $delivery = DeliveryMethod::query()->find($this->integer('delivery_method_id'));
                $branch = $this->route('branch');

                if (! $delivery instanceof DeliveryMethod || ! $delivery->uses_delivery_schedule || ! $branch instanceof Branch) {
                    return;
                }

                $schedule = DeliverySchedule::query()->find($this->input('delivery_schedule_id'));
                $preferredOn = $this->input('preferred_on');

                if (! $schedule instanceof DeliverySchedule || ! $schedule->isPreferredSlotFor($branch->id) || ! is_string($preferredOn) || ! $schedule->includesDate($preferredOn)) {
                    $validator->errors()->add('delivery_schedule_id', 'Choose a preferred slot.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $fee = $this->input('delivery_fee');

        $this->merge([
            'delivery_method_id' => $this->filled('delivery_method_id') ? $this->input('delivery_method_id') : null,
            'delivery_schedule_id' => $this->filled('delivery_schedule_id') ? $this->input('delivery_schedule_id') : null,
            'preferred_on' => $this->filled('preferred_on') ? $this->input('preferred_on') : null,
            'delivery_fee' => ($fee === '' || $fee === null) ? null : $fee,
            'pos_open_order_id' => $this->filled('pos_open_order_id') ? $this->input('pos_open_order_id') : null,
            'hotel_stay_id' => $this->filled('hotel_stay_id') ? $this->input('hotel_stay_id') : null,
            'apply_hotel_payment' => $this->boolean('apply_hotel_payment'),
        ]);
    }
}
