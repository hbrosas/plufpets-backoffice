<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class BranchInstructions
{
    /**
     * @param  list<array{branch_id: int, instructions: string|null, images?: list<mixed>, remove_images?: list<mixed>}>  $instructions
     */
    public static function sync(Model $method, array $instructions): void
    {
        $kept = [];

        foreach ($instructions as $index => $row) {
            $kept[] = (int) $row['branch_id'];
            $attributes = ['instructions' => $row['instructions']];

            if (array_key_exists('images', $row) || array_key_exists('remove_images', $row)) {
                $current = $method->branchInstructions()->where('branch_id', $row['branch_id'])->first();
                $attributes['images'] = InstructionImages::replace(
                    is_array($current?->images) ? $current->images : [],
                    is_array($row['images'] ?? null) ? $row['images'] : [],
                    is_array($row['remove_images'] ?? null) ? $row['remove_images'] : [],
                    "branch_instructions.{$index}.images",
                );
            }

            $method->branchInstructions()->updateOrCreate(
                ['branch_id' => $row['branch_id']],
                $attributes,
            );
        }

        $remaining = $method->branchInstructions();

        if ($kept !== []) {
            $remaining->whereNotIn('branch_id', $kept);
        }

        $remaining->delete();
    }
}
