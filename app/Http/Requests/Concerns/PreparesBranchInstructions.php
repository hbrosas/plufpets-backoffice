<?php

namespace App\Http\Requests\Concerns;

use App\Support\InstructionImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

trait PreparesBranchInstructions
{
    protected function sharedInstructionFlag(): bool
    {
        return $this->exists('instructions_for_all_branches')
            ? $this->boolean('instructions_for_all_branches')
            : true;
    }

    /**
     * @return list<array{branch_id: mixed, instructions: string|null, images?: list<mixed>, remove_images?: list<mixed>}>
     */
    protected function branchInstructions(): array
    {
        $rows = array_values(is_array($this->input('branch_instructions', [])) ? $this->input('branch_instructions', []) : []);

        return array_map(function (mixed $row, int $index): array {
            $instructions = trim((string) (is_array($row) ? ($row['instructions'] ?? '') : ''));
            $prepared = [
                'branch_id' => is_array($row) ? ($row['branch_id'] ?? null) : null,
                'instructions' => $instructions === '' ? null : $instructions,
            ];
            $uploads = InstructionImages::uploads($this->file("branch_instructions.{$index}.images"));
            $remove = $this->removedImages(is_array($row) ? ($row['remove_images'] ?? []) : []);

            if ($uploads !== [] || $remove !== []) {
                $prepared['images'] = $uploads;
                $prepared['remove_images'] = $remove;
            }

            return $prepared;
        }, $rows, array_keys($rows));
    }

    protected function guardInstructionImageLimit(Validator $validator, ?Model $method): void
    {
        $existing = is_array($method?->getAttribute('instruction_images')) ? $method->getAttribute('instruction_images') : [];
        $remove = $this->removedImages($this->input('remove_images', []));
        $kept = count(array_filter(
            $existing,
            fn (mixed $path): bool => is_string($path) && ! in_array($path, $remove, true),
        ));

        if ($kept + count(InstructionImages::uploads($this->file('images'))) > InstructionImages::Limit) {
            $validator->errors()->add('images', 'You can add up to 4 photos.');
        }

        if ($method !== null && method_exists($method, 'loadMissing')) {
            $method->loadMissing('branchInstructions');
        }

        $rows = array_values(is_array($this->input('branch_instructions')) ? $this->input('branch_instructions') : []);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $branchId = (int) ($row['branch_id'] ?? 0);
            $current = $method !== null && method_exists($method, 'branchInstructions')
                ? $method->branchInstructions->firstWhere('branch_id', $branchId)
                : null;
            $stored = is_array($current?->images) ? $current->images : [];
            $removed = $this->removedImages($row['remove_images'] ?? []);
            $remaining = count(array_filter(
                $stored,
                fn (mixed $path): bool => is_string($path) && ! in_array($path, $removed, true),
            ));

            if ($remaining + count(InstructionImages::uploads($this->file("branch_instructions.{$index}.images"))) > InstructionImages::Limit) {
                $validator->errors()->add("branch_instructions.{$index}.images", 'You can add up to 4 photos.');
            }
        }
    }

    protected function prepareInstructionImages(): void
    {
        if (! $this->hasFile('images')) {
            $this->merge(['images' => []]);
        }

        $this->merge([
            'remove_images' => $this->removedImages($this->input('remove_images', [])),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function removedImages(mixed $paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }
}
