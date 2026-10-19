<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesModifierData;
use App\Support\ModifierRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreModifierRequest extends FormRequest
{
    use PreparesModifierData;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ModifierRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ModifierRules::messages();
    }
}
