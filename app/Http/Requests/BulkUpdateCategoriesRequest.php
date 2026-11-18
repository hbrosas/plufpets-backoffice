<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCategoriesRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'action' => ['required', Rule::in(['pos_category', 'visibility'])],
            'pos_category_id' => ['integer', 'exists:pos_categories,id', 'required_if:action,pos_category'],
            'show_on_shop' => ['boolean', 'required_if:action,visibility'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Choose a category.',
            'ids.min' => 'Choose a category.',
            'ids.*.exists' => 'Choose a category from the list.',
            'action.required' => 'Choose an action.',
            'pos_category_id.required_if' => 'Choose a POS category.',
            'pos_category_id.exists' => 'Choose a POS category.',
            'show_on_shop.required_if' => 'Choose public shop visibility.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('show_on_shop')) {
            $this->merge([
                'show_on_shop' => $this->boolean('show_on_shop'),
            ]);
        }
    }
}
