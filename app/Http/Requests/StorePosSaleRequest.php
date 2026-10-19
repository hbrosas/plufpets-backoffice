<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Support\PosSaleRules;
use Illuminate\Contracts\Validation\ValidationRule;
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
