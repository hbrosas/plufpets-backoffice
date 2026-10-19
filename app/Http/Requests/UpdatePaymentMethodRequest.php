<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Support\PaymentMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
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
        $paymentMethod = $this->route('payment_method');

        return PaymentMethodRules::for($paymentMethod instanceof PaymentMethod ? $paymentMethod : null);
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
