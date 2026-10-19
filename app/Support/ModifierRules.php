<?php

namespace App\Support;

use App\Models\Modifier;
use Illuminate\Validation\Rule;

class ModifierRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?Modifier $modifier = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('modifiers', 'name')->ignore($modifier?->id),
            ],
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a modifier name.',
            'name.unique' => 'That modifier already exists.',
            'options.required' => 'Add at least one option.',
            'options.min' => 'Add at least one option.',
            'options.*.required' => 'Enter an option name.',
            'options.*.distinct' => 'Each option must be different.',
        ];
    }
}
