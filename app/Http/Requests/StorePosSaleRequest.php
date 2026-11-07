<?php

namespace App\Http\Requests;

use App\Enums\ShopOrderStatus;
use App\Models\Branch;
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
        return PosSaleRules::messages();
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $fee = $this->input('delivery_fee');

        $this->merge([
            'delivery_method_id' => $this->filled('delivery_method_id') ? $this->input('delivery_method_id') : null,
            'delivery_fee' => ($fee === '' || $fee === null) ? null : $fee,
            'pos_open_order_id' => $this->filled('pos_open_order_id') ? $this->input('pos_open_order_id') : null,
        ]);
    }
}
