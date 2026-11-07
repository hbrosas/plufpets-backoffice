<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesBranchInstructions;
use App\Models\PaymentMethod;
use App\Support\PaymentMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentMethodRequest extends FormRequest
{
    use PreparesBranchInstructions;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paymentMethod = $this->route('payment_method');

            $this->guardInstructionImageLimit(
                $validator,
                $paymentMethod instanceof PaymentMethod ? $paymentMethod : null,
            );
        });
    }

    protected function prepareForValidation(): void
    {
        $instructions = $this->string('instructions')->trim()->toString();
        $fee = $this->input('fee');

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'instructions' => $instructions === '' ? null : $instructions,
            'instructions_for_all_branches' => $this->sharedInstructionFlag(),
            'branch_instructions' => $this->branchInstructions(),
            'fee' => ($fee === '' || $fee === null) ? null : $fee,
            'is_cash' => $this->boolean('is_cash'),
            'is_active' => $this->boolean('is_active'),
        ]);
        $this->prepareInstructionImages();
    }
}
