<?php

namespace App\Http\Requests;

use App\Support\PosShiftRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClosePosShiftRequest extends FormRequest
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
        return PosShiftRules::close();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PosShiftRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_variance' => $this->boolean('confirm_variance'),
        ]);
    }
}
