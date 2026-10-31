<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:32'],
            'daily_sales_target' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the business name.',
            'daily_sales_target.min' => 'Enter a daily target of 0 or more.',
            'daily_sales_target.numeric' => 'Enter a daily target of 0 or more.',
            'email.email' => 'Enter a valid email address.',
            'logo.image' => 'Choose an image for the logo.',
            'logo.mimes' => 'Choose a JPG, PNG, or WebP logo.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'favicon.image' => 'Choose an image for the favicon.',
            'favicon.mimes' => 'Choose a JPG, PNG, or WebP favicon.',
            'favicon.max' => 'The favicon must be 512 KB or smaller.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [
            'name' => trim((string) $this->input('name', '')),
        ];

        foreach (['address', 'phone', 'email', 'tin', 'daily_sales_target'] as $field) {
            $value = trim((string) $this->input($field, ''));
            $merged[$field] = $value === '' ? null : $value;
        }

        $this->merge($merged);
    }
}
