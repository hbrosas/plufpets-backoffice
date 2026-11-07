<?php

namespace App\Http\Requests;

use App\Support\EmployeeProfileRules;
use App\Support\EmployeeSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateMyEmployeeRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = EmployeeSession::current();

        return is_array($employee) && $employee['id'] !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(EmployeeProfileRules::personal(), [
            'job_title',
            'employment_status',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return EmployeeProfileRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (EmployeeProfileRules::personalFields() as $field) {
            $value = trim((string) $this->input($field, ''));
            $merged[$field] = $value === '' ? null : $value;
        }

        $this->merge($merged);
    }
}
