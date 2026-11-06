<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopPromotionRequest extends FormRequest
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
            'visible' => ['required', 'boolean'],
            'text' => [Rule::requiredIf(fn (): bool => $this->boolean('visible')), 'nullable', 'string', 'max:200'],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Enter the promotional text.',
            'text.max' => 'Keep the promotional text to 200 characters.',
            'background_color.regex' => 'Choose a background color.',
            'text_color.regex' => 'Choose a text color.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $text = trim((string) $this->input('text', ''));

        $this->merge([
            'visible' => $this->boolean('visible'),
            'text' => $text === '' ? null : $text,
            'background_color' => strtolower(trim((string) $this->input('background_color', ''))),
            'text_color' => strtolower(trim((string) $this->input('text_color', ''))),
        ]);
    }
}
