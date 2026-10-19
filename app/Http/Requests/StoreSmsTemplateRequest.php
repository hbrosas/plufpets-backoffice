<?php

namespace App\Http\Requests;

use App\Support\PosSmsRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSmsTemplateRequest extends FormRequest
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
        return PosSmsRules::template();
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
            'name' => $this->string('name')->trim()->toString(),
            'body' => $this->string('body')->trim()->toString(),
        ]);
    }
}
