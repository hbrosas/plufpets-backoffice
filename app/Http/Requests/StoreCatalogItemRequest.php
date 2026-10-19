<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesCatalogItemData;
use App\Support\CatalogItemRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    use PreparesCatalogItemData;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return CatalogItemRules::for();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CatalogItemRules::messages();
    }
}
