<?php

namespace App\Http\Requests;

use App\Support\SalesFilters;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesFilterRequest extends FormRequest
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
            'preset' => ['required', Rule::in(['today', '7d', '30d', 'custom'])],
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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'search' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function filters(): SalesFilters
    {
        $preset = (string) $this->validated('preset');

        [$from, $to] = match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            '30d' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            default => [
                CarbonImmutable::parse((string) $this->validated('from'))->startOfDay(),
                CarbonImmutable::parse((string) $this->validated('to'))->endOfDay(),
            ],
        };

        return new SalesFilters(
            preset: $preset,
            from: $from,
            to: $to,
            branchId: $this->validated('branch_id'),
            search: (string) ($this->validated('search') ?? ''),
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preset' => $this->string('preset')->trim()->toString() ?: 'today',
            'from' => $this->string('from')->trim()->toString() ?: null,
            'to' => $this->string('to')->trim()->toString() ?: null,
            'branch_id' => $this->integer('branch_id') ?: null,
            'search' => $this->string('search')->trim()->toString() ?: null,
        ]);
    }
}
