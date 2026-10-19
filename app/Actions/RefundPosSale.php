<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Models\BranchInventory;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPosSale
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     */
    public function handle(PosSale $sale, string $reason, array $employee): PosSale
    {
        return DB::transaction(function () use ($sale, $reason, $employee): PosSale {
            $sale = PosSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->refunded_at !== null) {
                throw ValidationException::withMessages([
                    'status' => 'This receipt has already been refunded.',
                ]);
            }

            $sale->load(['items.variant.catalogItem']);

            foreach ($sale->items as $item) {
                $this->restoreStock($sale->branch_id, $item);
            }

            $sale->fill([
                'refunded_at' => now(),
                'refunded_by_employee_number' => $employee['employee_number'],
                'refunded_by_name' => $employee['name'],
                'refund_reason' => $reason,
            ])->save();

            return $sale->refresh();
        });
    }

    private function restoreStock(int $branchId, PosSaleItem $item): void
    {
        $variant = $item->variant;

        if ($variant === null || $variant->catalogItem?->type !== CatalogItemType::Product) {
            return;
        }

        $inventory = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('catalog_item_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            BranchInventory::query()->create([
                'branch_id' => $branchId,
                'catalog_item_variant_id' => $variant->id,
                'quantity' => $item->quantity,
            ]);

            return;
        }

        $inventory->increment('quantity', $item->quantity);
    }
}
