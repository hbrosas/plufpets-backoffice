<?php

namespace App\Http\Requests;

use App\Support\PaymentMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
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
        return PaymentMethodRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PaymentMethodRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $instructions = $this->string('instructions')->trim()->toString();

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'instructions' => $instructions === '' ? null : $instructions,
            'is_cash' => $this->boolean('is_cash'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
