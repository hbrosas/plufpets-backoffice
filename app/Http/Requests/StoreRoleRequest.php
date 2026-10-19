<?php

namespace App\Http\Requests;

use App\Support\RoleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
        return RoleRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RoleRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'permissions' => $this->input('permissions', []),
        ]);
    }
}
