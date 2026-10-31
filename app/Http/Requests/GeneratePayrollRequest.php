<?php

namespace App\Http\Requests;

use App\Support\PayrollRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PayrollRules::prepare($this->all()));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return PayrollRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PayrollRules::messages();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                PayrollRules::after($validator);
            },
        ];
    }
}
