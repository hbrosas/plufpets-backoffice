<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetAccountRequest extends FormRequest
{
    public const CONFIRMATION = 'DELETE ACCOUNT';

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
            'confirmation' => ['required', 'in:'.self::CONFIRMATION],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Type DELETE ACCOUNT to reset the account.',
            'confirmation.in' => 'Type DELETE ACCOUNT to reset the account.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation' => $this->string('confirmation')->trim()->toString(),
        ]);
    }
}
