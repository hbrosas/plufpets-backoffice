<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCatalogItemsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:catalog_items,id'],
            'action' => ['required', Rule::in(['category', 'brand', 'status', 'preorder', 'delete'])],
            'category_name' => [
                'nullable',
                'string',
                'max:255',
                'required_if:action,category',
                Rule::exists('categories', 'name'),
            ],
            'brand_name' => ['nullable', 'string', 'max:255', Rule::exists('brands', 'name')],
            'is_active' => ['boolean', 'required_if:action,status'],
            'allows_preorder' => ['boolean', 'required_if:action,preorder'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Choose a product.',
            'ids.min' => 'Choose a product.',
            'action.required' => 'Choose an action.',
            'category_name.required_if' => 'Choose a category.',
            'category_name.exists' => 'Choose a category from the list.',
            'brand_name.exists' => 'Choose a brand from the list.',
            'is_active.required_if' => 'Choose a status.',
            'allows_preorder.required_if' => 'Choose whether preorder is allowed.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->exists('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($this->exists('allows_preorder')) {
            $merge['allows_preorder'] = $this->boolean('allows_preorder');
        }

        if ($this->input('action') === 'brand' && $this->input('brand_name') === '') {
            $merge['brand_name'] = null;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
