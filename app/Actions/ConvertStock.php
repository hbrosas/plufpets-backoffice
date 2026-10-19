<?php

namespace App\Actions;

use App\Models\BranchInventory;
use App\Models\StockConversion;
use App\Support\InventoryCosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertStock
{
    public function __construct(public InventoryCosts $inventoryCosts) {}

    /**
     * @param  array{branch_id: int, from_catalog_item_variant_id: int, to_catalog_item_variant_id: int, quantity: int, pieces_per_unit: int, notes?: string|null}  $attributes
     */
    public function handle(array $attributes): StockConversion
    {
        if ($attributes['from_catalog_item_variant_id'] === $attributes['to_catalog_item_variant_id']) {
            throw ValidationException::withMessages([
                'to_catalog_item_variant_id' => 'Choose a different product to convert into.',
            ]);
        }

        $toQuantity = $attributes['quantity'] * $attributes['pieces_per_unit'];
        $sourceCost = $this->inventoryCosts->latestUnitCost($attributes['from_catalog_item_variant_id']);
        $unitCost = $sourceCost === null
            ? null
            : number_format(($sourceCost * $attributes['quantity']) / $toQuantity, 2, '.', '');

        return DB::transaction(function () use ($attributes, $toQuantity, $unitCost): StockConversion {
            $source = BranchInventory::query()
                ->where('branch_id', $attributes['branch_id'])
                ->where('catalog_item_variant_id', $attributes['from_catalog_item_variant_id'])
                ->lockForUpdate()
                ->first();

            if ($source === null || $source->quantity < $attributes['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'That branch does not have enough stock to convert.',
                ]);
            }

            $source->update([
                'quantity' => $source->quantity - $attributes['quantity'],
            ]);

            $destination = BranchInventory::query()
                ->where('branch_id', $attributes['branch_id'])
                ->where('catalog_item_variant_id', $attributes['to_catalog_item_variant_id'])
                ->lockForUpdate()
                ->first();

            if ($destination === null) {
                BranchInventory::query()->create([
                    'branch_id' => $attributes['branch_id'],
                    'catalog_item_variant_id' => $attributes['to_catalog_item_variant_id'],
                    'quantity' => $toQuantity,
                ]);
            } else {
                $destination->update([
                    'quantity' => $destination->quantity + $toQuantity,
                ]);
            }

            return StockConversion::query()->create([
                'branch_id' => $attributes['branch_id'],
                'from_catalog_item_variant_id' => $attributes['from_catalog_item_variant_id'],
                'to_catalog_item_variant_id' => $attributes['to_catalog_item_variant_id'],
                'from_quantity' => $attributes['quantity'],
                'to_quantity' => $toQuantity,
                'unit_cost' => $unitCost,
                'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }
}
