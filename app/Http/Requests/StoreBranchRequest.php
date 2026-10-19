<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'registered_business_name' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:32'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('branches', 'code')],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a branch name.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->toString();

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'registered_business_name' => $this->filled('registered_business_name')
                ? $this->string('registered_business_name')->trim()->toString()
                : null,
            'tin' => $this->filled('tin') ? $this->string('tin')->trim()->toString() : null,
            'code' => $code === '' ? null : $code,
            'address' => $this->filled('address') ? $this->string('address')->trim()->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
