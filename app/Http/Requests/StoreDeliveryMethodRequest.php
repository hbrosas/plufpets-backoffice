<?php

namespace App\Http\Requests;

use App\Support\DeliveryMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryMethodRequest extends FormRequest
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
        return DeliveryMethodRules::for();
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
