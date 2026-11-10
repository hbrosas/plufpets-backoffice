<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreBackofficeHotelStayRequest extends StoreHotelStayRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'branch_id.required' => 'Choose a branch.',
            'branch_id.exists' => 'Choose a branch.',
        ];
    }
}
