<?php

namespace App\Actions;

use App\Models\BranchInventory;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferStock
{
    /**
     * @param  array{from_branch_id: int, to_branch_id: int, catalog_item_variant_id: int, quantity: int, notes?: string|null}  $attributes
     */
    public function handle(array $attributes): StockTransfer
    {
        if ($attributes['from_branch_id'] === $attributes['to_branch_id']) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Choose a different destination branch.',
            ]);
        }

        return DB::transaction(function () use ($attributes): StockTransfer {
            $source = BranchInventory::query()
                ->where('branch_id', $attributes['from_branch_id'])
                ->where('catalog_item_variant_id', $attributes['catalog_item_variant_id'])
                ->lockForUpdate()
                ->first();

            if ($source === null || $source->quantity < $attributes['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'That branch does not have enough stock to transfer.',
                ]);
            }

            $source->update([
                'quantity' => $source->quantity - $attributes['quantity'],
            ]);

            $destination = BranchInventory::query()
                ->where('branch_id', $attributes['to_branch_id'])
                ->where('catalog_item_variant_id', $attributes['catalog_item_variant_id'])
                ->lockForUpdate()
                ->first();

            if ($destination === null) {
                BranchInventory::query()->create([
                    'branch_id' => $attributes['to_branch_id'],
                    'catalog_item_variant_id' => $attributes['catalog_item_variant_id'],
                    'quantity' => $attributes['quantity'],
                ]);
            } else {
                $destination->update([
                    'quantity' => $destination->quantity + $attributes['quantity'],
                ]);
            }

            return StockTransfer::query()->create([
                'from_branch_id' => $attributes['from_branch_id'],
                'to_branch_id' => $attributes['to_branch_id'],
                'catalog_item_variant_id' => $attributes['catalog_item_variant_id'],
                'quantity' => $attributes['quantity'],
                'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }
}
