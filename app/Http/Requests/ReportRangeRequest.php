<?php

namespace App\Http\Requests;

use App\Support\ReportPeriod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRangeRequest extends FormRequest
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
            'preset' => ['required', Rule::in(['day', 'week', 'month', 'year', 'custom'])],
            'from' => ['nullable', 'required_if:preset,custom', 'date'],
            'to' => [
                'nullable',
                'required_if:preset,custom',
                'date',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $from = $this->date('from');
                    $to = $this->date('to');

                    if ($from && $to && $from->diffInDays($to) > 366) {
                        $fail('Choose a range of 366 days or less.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preset.in' => 'Choose a valid period.',
            'from.required_if' => 'Choose a start date.',
            'to.required_if' => 'Choose an end date.',
            'to.after_or_equal' => 'Choose an end date on or after the start date.',
        ];
    }

    public function period(): ReportPeriod
    {
        return ReportPeriod::report(
            (string) $this->validated('preset'),
            $this->validated('from'),
            $this->validated('to'),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preset' => $this->string('preset')->trim()->toString() ?: 'month',
            'from' => $this->string('from')->trim()->toString() ?: null,
            'to' => $this->string('to')->trim()->toString() ?: null,
        ]);
    }
}
