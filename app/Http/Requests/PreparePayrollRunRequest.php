<?php

namespace App\Http\Requests;

use App\Support\PayrollRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PreparePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('deduct_government')) {
            $this->merge(['deduct_government' => true]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'deduct_government' => ['required', 'boolean'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_start.required' => 'Enter the payroll start date.',
            'period_start.date' => 'Enter the payroll start date.',
            'period_end.required' => 'Enter the payroll end date.',
            'period_end.date' => 'Enter the payroll end date.',
            'period_end.after_or_equal' => 'The end date must be on or after the start date.',
            'deduct_government.required' => 'Choose whether to deduct government benefits.',
            'deduct_government.boolean' => 'Choose whether to deduct government benefits.',
            'employee_ids.required' => 'Choose at least one employee.',
            'employee_ids.min' => 'Choose at least one employee.',
            'employee_ids.*.exists' => 'Choose an employee on the list.',
            'employee_ids.*.distinct' => 'Each employee can only be selected once.',
        ];
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                PayrollRules::afterSelection($validator);
            },
        ];
    }
}
