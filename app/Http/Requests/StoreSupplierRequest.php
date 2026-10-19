<?php

namespace App\Http\Requests;

use App\Support\SupplierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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
        return SupplierRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return SupplierRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->toString();

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'code' => $code === '' ? null : $code,
            'contact_name' => $this->filled('contact_name') ? $this->string('contact_name')->trim()->toString() : null,
            'email' => $this->filled('email') ? $this->string('email')->trim()->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'address' => $this->filled('address') ? $this->string('address')->trim()->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->toString() : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
