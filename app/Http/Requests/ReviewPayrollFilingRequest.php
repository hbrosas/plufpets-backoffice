<?php

namespace App\Http\Requests;

use App\Enums\PayrollFilingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPayrollFilingRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                PayrollFilingStatus::Approved->value,
                PayrollFilingStatus::Rejected->value,
            ])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_note' => $this->string('review_note')->trim()->toString() ?: null,
        ]);
    }
}
