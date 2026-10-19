<?php

namespace App\Http\Requests;

use App\Models\DeliveryMethod;
use App\Support\DeliveryMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryMethodRequest extends FormRequest
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
        $deliveryMethod = $this->route('delivery_method');

        return DeliveryMethodRules::for($deliveryMethod instanceof DeliveryMethod ? $deliveryMethod : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DeliveryMethodRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->string('notes')->trim()->toString();

        $fee = $this->input('fee');

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'fee' => ($fee === '' || $fee === null) ? null : $fee,
            'notes' => $notes === '' ? null : $notes,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
