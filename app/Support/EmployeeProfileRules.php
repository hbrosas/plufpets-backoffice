<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\PayBasis;
use Illuminate\Validation\Rule;

class EmployeeProfileRules
{
    /**
     * @return list<string>
     */
    public static function personalFields(): array
    {
        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    public static function personal(): array
    {
        return array_intersect_key(self::for(), array_flip(self::personalFields()));
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'civil_status' => ['nullable', Rule::enum(CivilStatus::class)],
            'address' => ['nullable', 'string', 'max:1000'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:30'],
            'sss' => ['nullable', 'string', 'max:30'],
            'philhealth' => ['nullable', 'string', 'max:30'],
            'pagibig' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_status' => ['nullable', Rule::enum(EmploymentStatus::class)],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'pay_basis' => ['required', Rule::enum(PayBasis::class)],
            'overnight_allowance' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'compensation_lines' => ['present', 'array', 'max:30'],
            'compensation_lines.*.name' => ['required', 'string', 'max:255'],
            'compensation_lines.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'benefit_lines' => ['present', 'array', 'max:30'],
            'benefit_lines.*.name' => ['required', 'string', 'max:255'],
            'benefit_lines.*.amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'government_lines' => ['present', 'array', 'max:30'],
            'government_lines.*.name' => ['required', 'string', 'max:255'],
            'government_lines.*.employee_contribution' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'government_lines.*.employer_contribution' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999.99'],
            'commissions' => ['present', 'array'],
            'commissions.*.catalog_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('catalog_items', 'id')->where(
                    fn ($query) => $query->where('type', CatalogItemType::Service->value)->where('is_active', true),
                ),
            ],
            'commissions.*.assigned_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'commissions.*.assisted_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.email' => 'Enter a valid email address.',
            'pay_basis.required' => 'Choose per day or per month.',
            'overnight_allowance.required' => 'Enter the overnight allowance.',
            'overnight_allowance.numeric' => 'Enter the overnight allowance.',
            'overnight_allowance.min' => 'Overnight allowance cannot be negative.',
            'overnight_allowance.decimal' => 'Overnight allowance can have at most 2 decimal places.',
            'compensation_lines.*.name.required' => 'Enter a compensation name.',
            'compensation_lines.*.amount.required' => 'Enter a compensation amount.',
            'compensation_lines.*.amount.min' => 'Compensation cannot be negative.',
            'benefit_lines.*.name.required' => 'Enter a benefit name.',
            'benefit_lines.*.amount.required' => 'Enter a benefit amount.',
            'benefit_lines.*.amount.min' => 'A benefit cannot be negative.',
            'government_lines.*.name.required' => 'Enter a benefit name.',
            'government_lines.*.employee_contribution.required' => 'Enter the employee contribution.',
            'government_lines.*.employee_contribution.min' => 'The employee contribution cannot be negative.',
            'government_lines.*.employer_contribution.required' => 'Enter the employer contribution.',
            'government_lines.*.employer_contribution.min' => 'The employer contribution cannot be negative.',
            'commissions.*.catalog_item_id.exists' => 'Choose an active service.',
            'commissions.*.catalog_item_id.distinct' => 'Each service can only be listed once.',
            'commissions.*.assigned_rate.max' => 'Assigned commission cannot be more than 100%.',
            'commissions.*.assisted_rate.max' => 'Assisted commission cannot be more than 100%.',
        ];
    }
}
