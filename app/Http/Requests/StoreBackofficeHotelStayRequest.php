<?php

namespace App\Http\Requests;

use App\Support\TestingMode;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreBackofficeHotelStayRequest extends StoreHotelStayRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'branch_id' => ['required', 'integer', TestingMode::existsBranch(activeOnly: true)],
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
