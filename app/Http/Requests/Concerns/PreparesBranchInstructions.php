<?php

namespace App\Http\Requests\Concerns;

trait PreparesBranchInstructions
{
    protected function sharedInstructionFlag(): bool
    {
        return $this->exists('instructions_for_all_branches')
            ? $this->boolean('instructions_for_all_branches')
            : true;
    }

    /**
     * @return list<array{branch_id: mixed, instructions: string|null}>
     */
    protected function branchInstructions(): array
    {
        $rows = $this->input('branch_instructions', []);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(function (mixed $row): array {
            $instructions = trim((string) (is_array($row) ? ($row['instructions'] ?? '') : ''));

            return [
                'branch_id' => is_array($row) ? ($row['branch_id'] ?? null) : null,
                'instructions' => $instructions === '' ? null : $instructions,
            ];
        }, $rows));
    }
}
