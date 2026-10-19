<?php

namespace App\Http\Requests;

use App\Models\TaxFee;
use App\Support\TaxFeeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxFeeRequest extends FormRequest
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
        $taxFee = $this->route('tax_fee');

        return TaxFeeRules::for($taxFee instanceof TaxFee ? $taxFee : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TaxFeeRules::messages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'type' => $this->string('type')->trim()->lower()->toString(),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
