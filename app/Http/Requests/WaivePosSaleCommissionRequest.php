<?php

namespace App\Http\Requests;

use App\Models\PosSale;
use App\Models\PosSaleItemCommission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class WaivePosSaleCommissionRequest extends FormRequest
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
            'employee_id' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Choose an employee.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $value = $this->string('employee_id')->toString();

                if ($value === 'all') {
                    return;
                }

                if (! ctype_digit($value)) {
                    $validator->errors()->add('employee_id', 'Choose an employee.');

                    return;
                }

                $sale = $this->route('pos_sale');

                if (! $sale instanceof PosSale) {
                    return;
                }

                $onReceipt = PosSaleItemCommission::query()
                    ->where('employee_id', (int) $value)
                    ->whereHas('saleItem', fn ($query) => $query->where('pos_sale_id', $sale->id))
                    ->exists();

                if (! $onReceipt) {
                    $validator->errors()->add('employee_id', 'Choose an employee on this receipt.');
                }
            },
        ];
    }
}
