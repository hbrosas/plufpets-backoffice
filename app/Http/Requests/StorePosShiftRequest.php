<?php

namespace App\Http\Requests;

use App\Support\PosShiftRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePosShiftRequest extends FormRequest
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
        return PosShiftRules::open();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PosShiftRules::messages();
    }
}
