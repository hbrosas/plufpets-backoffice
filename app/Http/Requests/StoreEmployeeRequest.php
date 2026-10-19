<?php

namespace App\Http\Requests;

use App\Support\EmployeeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
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
        return EmployeeRules::for(pinRequired: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return EmployeeRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'pin' => $this->string('pin')->trim()->toString(),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                EmployeeRules::guardHomeScreen($validator);
            },
        ];
    }
}
