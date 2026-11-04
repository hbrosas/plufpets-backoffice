<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PosOpenOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceShopOrder
{
    /**
     * @param  list<array{catalog_item_variant_id: int, quantity: int}>  $items
     */
    public function handle(Branch $branch, string $name, string $phone, array $items): PosOpenOrder
    {
        return DB::transaction(function () use ($branch, $name, $phone, $items): PosOpenOrder {
            $lines = [];
            $subtotal = 0.0;

            foreach ($items as $item) {
                $variant = CatalogItemVariant::query()
                    ->with([
                        'catalogItem:id,name,type,has_branch_pricing,is_active',
                        'branchPrices' => fn ($query) => $query->where('branch_id', $branch->id),
                    ])
                    ->whereKey($item['catalog_item_variant_id'])
                    ->lockForUpdate()
                    ->first();

                if ($variant === null || ! $variant->is_active || ! $variant->catalogItem?->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'That item is not available.',
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $isProduct = $variant->catalogItem->type === CatalogItemType::Product;

                if ($isProduct) {
                    $inventory = BranchInventory::query()
                        ->where('branch_id', $branch->id)
                        ->where('catalog_item_variant_id', $variant->id)
                        ->lockForUpdate()
                        ->first();
                    $onHand = (int) ($inventory?->quantity ?? 0);

                    if ($onHand < 1) {
                        throw ValidationException::withMessages([
                            'items' => $this->productName($variant).' is out of stock.',
                        ]);
                    }

                    if ($quantity > $onHand) {
                        throw ValidationException::withMessages([
                            'items' => 'Only '.$onHand.' left for '.$this->productName($variant).'.',
                        ]);
                    }
                }

                $price = $this->price($variant);
                $subtotal += $price * $quantity;
                $lines[] = [
                    'catalog_item_variant_id' => $variant->id,
                    'product_name' => $variant->catalogItem->name,
                    'variant_name' => $variant->name,
                    'price' => number_format($price, 2, '.', ''),
                    'quantity' => $quantity,
                    'notes' => null,
                    'is_product' => $isProduct,
                    'modifiers' => [],
                    'assigned_employee_ids' => [],
                    'assisted_employee_ids' => [],
                ];
            }

            return PosOpenOrder::query()->create([
                'branch_id' => $branch->id,
                'number' => $this->nextNumber($branch->id),
                'name' => $name.' · '.$phone,
                'items' => $lines,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'saved_by_employee_number' => '',
                'saved_by_name' => $name,
            ]);
        });
    }

    private function price(CatalogItemVariant $variant): float
    {
        if ($variant->catalogItem?->has_branch_pricing) {
            $branchPrice = $variant->branchPrices->first();

            if ($branchPrice !== null) {
                return (float) $branchPrice->price;
            }
        }

        return (float) $variant->price;
    }

    private function productName(CatalogItemVariant $variant): string
    {
        $name = $variant->catalogItem?->name ?? 'That item';
        $variantName = trim((string) $variant->name);

        if ($variantName !== '' && strcasecmp($variantName, 'Default') !== 0) {
            return $name.' · '.$variantName;
        }

        return $name;
    }

    private function nextNumber(int $branchId): string
    {
        $prefix = now()->format('Ymd');
        $count = PosOpenOrder::query()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->lockForUpdate()
            ->count();

        return sprintf('OPEN-%s-%04d', $prefix, $count + 1);
    }
}
