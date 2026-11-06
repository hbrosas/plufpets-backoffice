<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopBannerRequest extends FormRequest
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:width=1600,height=500'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.image' => 'Choose an image for the banner.',
            'image.mimes' => 'Choose a JPG, PNG, or WebP banner.',
            'image.max' => 'The banner image must be 4 MB or smaller.',
            'image.dimensions' => 'The banner must be 1600 by 500 pixels.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
