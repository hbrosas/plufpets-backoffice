<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(bool $pinRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'start_date' => ['required', 'date', 'after_or_equal:date_of_birth'],
            'pin' => [$pinRequired ? 'required' : 'nullable', 'string', 'digits_between:4,8'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'date_of_birth.required' => 'Enter a date of birth.',
            'date_of_birth.date' => 'Enter a valid date of birth.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'date_of_birth.after' => 'Enter a valid date of birth.',
            'start_date.required' => 'Enter a start date.',
            'start_date.date' => 'Enter a valid start date.',
            'start_date.after_or_equal' => 'Start date must be on or after the date of birth.',
            'pin.required' => 'Enter a PIN code.',
            'pin.digits_between' => 'The PIN code must be 4 to 8 digits.',
            'role_id.required' => 'Choose a role.',
            'role_id.exists' => 'Choose a role.',
        ];
    }
}
