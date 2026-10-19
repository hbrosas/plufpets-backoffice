<?php

namespace App\Http\Requests\Concerns;

trait PreparesModifierData
{
    protected function prepareForValidation(): void
    {
        $options = collect($this->input('options', []))
            ->map(function (mixed $option): string {
                if (is_array($option)) {
                    return trim((string) ($option['name'] ?? $option['value'] ?? ''));
                }

                return trim((string) $option);
            })
            ->filter()
            ->unique(fn (string $option): string => mb_strtolower($option))
            ->values()
            ->all();

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'is_active' => $this->boolean('is_active'),
            'options' => $options,
        ]);
    }
}
