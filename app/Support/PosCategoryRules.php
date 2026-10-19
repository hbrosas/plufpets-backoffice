<?php

namespace App\Support;

use App\Models\PosCategory;
use Illuminate\Validation\Rule;

class PosCategoryRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?PosCategory $posCategory = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pos_categories', 'name')->ignore($posCategory?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a POS category name.',
            'name.unique' => 'That POS category already exists.',
        ];
    }
}
