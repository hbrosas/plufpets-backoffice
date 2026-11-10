<?php

namespace App\Http\Requests;

use App\Support\HotelStayRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHotelStayPaymentRequest extends FormRequest
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
        return HotelStayRules::payment();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return HotelStayRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $reference = trim((string) $this->input('reference_number'));
        $tendered = $this->input('amount_tendered');

        $this->merge([
            'reference_number' => $reference === '' ? null : $reference,
            'amount_tendered' => ($tendered === '' || $tendered === null) ? null : $tendered,
        ]);
    }
}
