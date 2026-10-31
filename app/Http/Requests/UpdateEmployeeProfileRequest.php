<?php

namespace App\Http\Requests;

use App\Support\EmployeeProfileRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeProfileRequest extends FormRequest
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
        return EmployeeProfileRules::for();
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
        $nullable = [
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'civil_status',
            'address',
            'mobile',
            'email',
            'tin',
            'sss',
            'philhealth',
            'pagibig',
            'job_title',
            'employment_status',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
        ];

        $merged = [];

        foreach ($nullable as $field) {
            $value = trim((string) $this->input($field, ''));
            $merged[$field] = $value === '' ? null : $value;
        }

        $allowance = $this->input('overnight_allowance');
        $merged['overnight_allowance'] = ($allowance === '' || $allowance === null) ? '0' : $allowance;
        $merged['compensation_lines'] = $this->payLines('compensation_lines');
        $merged['benefit_lines'] = $this->payLines('benefit_lines');
        $merged['government_lines'] = $this->governmentLines();
        $merged['commissions'] = collect($this->input('commissions', []))
            ->map(function (mixed $row): array {
                if (! is_array($row)) {
                    return [
                        'catalog_item_id' => null,
                        'assigned_rate' => '0',
                        'assisted_rate' => '0',
                    ];
                }

                return [
                    'catalog_item_id' => $row['catalog_item_id'] ?? null,
                    'assigned_rate' => ($row['assigned_rate'] ?? '') === '' ? '0' : $row['assigned_rate'],
                    'assisted_rate' => ($row['assisted_rate'] ?? '') === '' ? '0' : $row['assisted_rate'],
                ];
            })
            ->all();

        $this->merge($merged);
    }

    /**
     * @return list<array{name: string, amount: mixed}>
     */
    private function payLines(string $key): array
    {
        return collect($this->input($key, []))
            ->map(function (mixed $row): array {
                if (! is_array($row)) {
                    return ['name' => '', 'amount' => null];
                }

                $amount = $row['amount'] ?? null;

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'amount' => $this->blankToNull($amount),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' || $row['amount'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, employee_contribution: mixed, employer_contribution: mixed}>
     */
    private function governmentLines(): array
    {
        return collect($this->input('government_lines', []))
            ->map(function (mixed $row): array {
                if (! is_array($row)) {
                    return [
                        'name' => '',
                        'employee_contribution' => null,
                        'employer_contribution' => null,
                    ];
                }

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'employee_contribution' => $this->blankToNull($row['employee_contribution'] ?? null),
                    'employer_contribution' => $this->blankToNull($row['employer_contribution'] ?? null),
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '' || $row['employee_contribution'] !== null || $row['employer_contribution'] !== null)
            ->values()
            ->all();
    }

    private function blankToNull(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }
}
