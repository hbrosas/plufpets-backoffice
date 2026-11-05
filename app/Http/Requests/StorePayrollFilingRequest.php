<?php

namespace App\Http\Requests;

use App\Enums\PayrollFilingType;
use App\Support\EmployeeSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollFilingRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::enum(PayrollFilingType::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'required_if:type,leave', 'date', 'after_or_equal:starts_on'],
            'hours' => ['nullable', 'required_if:type,overtime', 'numeric', 'min:0.01', 'max:24', 'decimal:0,2'],
            'nights' => ['nullable', 'required_if:type,overnight', 'integer', 'min:1', 'max:31'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_on.required' => 'Choose a date.',
            'ends_on.required_if' => 'Choose the last day of the leave.',
            'ends_on.after_or_equal' => 'Choose an end date on or after the start date.',
            'hours.required_if' => 'Enter the overtime hours.',
            'hours.min' => 'Overtime hours must be more than zero.',
            'hours.max' => 'Overtime hours cannot be more than 24.',
            'nights.required_if' => 'Enter the number of nights.',
            'nights.min' => 'Overnight nights must be at least 1.',
            'nights.max' => 'Overnight nights cannot be more than 31.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->string('type')->toString();

        $this->merge([
            'ends_on' => $type === PayrollFilingType::Leave->value ? ($this->input('ends_on') ?: null) : null,
            'hours' => $type === PayrollFilingType::Overtime->value ? ($this->input('hours') === '' ? null : $this->input('hours')) : null,
            'nights' => $type === PayrollFilingType::Overnight->value ? ($this->input('nights') === '' ? null : $this->input('nights')) : null,
            'reason' => $this->string('reason')->trim()->toString() ?: null,
        ]);
    }
}
