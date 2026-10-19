<?php

namespace App\Http\Requests;

use App\Support\PosSmsRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePosSmsRequest extends FormRequest
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
        return PosSmsRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PosSmsRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'number' => preg_replace('/[\s\-()]/', '', $this->string('number')->toString()) ?? '',
            'message' => $this->string('message')->trim()->toString(),
        ]);
    }
}
