<?php

namespace App\Support;

use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SupplierRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Supplier $supplier = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a supplier name.',
            'code.unique' => 'That supplier code is already used.',
            'email.email' => 'Enter a valid email address.',
        ];
    }
}
