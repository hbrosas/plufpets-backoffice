<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
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
            'employee_number' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'digits_between:4,8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.required' => 'Enter your employee number.',
            'pin.required' => 'Enter your PIN code.',
            'pin.digits_between' => 'The PIN code must be 4 to 8 digits.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_number' => $this->string('employee_number')->trim()->toString(),
            'pin' => $this->string('pin')->trim()->toString(),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $numberMatches = hash_equals(
                    (string) config('employee.number'),
                    $this->string('employee_number')->toString(),
                );

                $pinMatches = hash_equals(
                    (string) config('employee.pin'),
                    $this->string('pin')->toString(),
                );

                if (! $numberMatches || ! $pinMatches) {
                    $validator->errors()->add(
                        'employee_number',
                        'Employee number or PIN code is incorrect.',
                    );
                }
            },
        ];
    }
}
