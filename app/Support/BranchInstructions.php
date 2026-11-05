<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class BranchInstructions
{
    /**
     * @param  list<array{branch_id: int, instructions: string|null}>  $instructions
     */
    public static function sync(Model $method, array $instructions): void
    {
        $kept = [];

        foreach ($instructions as $row) {
            $kept[] = (int) $row['branch_id'];
            $method->branchInstructions()->updateOrCreate(
                ['branch_id' => $row['branch_id']],
                ['instructions' => $row['instructions']],
            );
        }

        $remaining = $method->branchInstructions();

        if ($kept !== []) {
            $remaining->whereNotIn('branch_id', $kept);
        }

        $remaining->delete();
    }
}
