<?php

namespace App\Http\Requests;

use App\Support\PosCashMovementRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePosCashMovementRequest extends FormRequest
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
        return PosCashMovementRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PosCashMovementRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->string('reason')->trim()->toString(),
        ]);
    }
}
