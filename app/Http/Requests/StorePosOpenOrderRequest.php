<?php

namespace App\Http\Requests;

use App\Support\PosOpenOrderRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePosOpenOrderRequest extends FormRequest
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
        return PosOpenOrderRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PosOpenOrderRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }
}
