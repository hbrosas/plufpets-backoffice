<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesBranchInstructions;
use App\Models\DeliveryMethod;
use App\Support\DeliveryMethodRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryMethodRequest extends FormRequest
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $deliveryMethod = $this->route('delivery_method');

            $this->guardInstructionImageLimit(
                $validator,
                $deliveryMethod instanceof DeliveryMethod ? $deliveryMethod : null,
            );
        });
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->string('notes')->trim()->toString();

        $fee = $this->input('fee');

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'fee' => ($fee === '' || $fee === null) ? null : $fee,
            'notes' => $notes === '' ? null : $notes,
            'instructions_for_all_branches' => $this->sharedInstructionFlag(),
            'branch_instructions' => $this->branchInstructions(),
            'asks_for_address' => $this->boolean('asks_for_address'),
            'uses_delivery_schedule' => $this->boolean('uses_delivery_schedule'),
            'is_active' => $this->boolean('is_active'),
        ]);
        $this->prepareInstructionImages();
    }
}
