<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesCatalogItemData;
use App\Models\CatalogItem;
use App\Support\CatalogItemRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
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
        $catalogItem = $this->route('catalog_item');

        return CatalogItemRules::for($catalogItem instanceof CatalogItem ? $catalogItem : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CatalogItemRules::messages();
    }
}
