<?php

namespace App\Actions;

use App\Enums\InventoryHold;
use App\Models\BranchInventory;
use App\Models\PosOpenOrder;
use Illuminate\Validation\ValidationException;

class ShopOrderStock
{
    public function hold(PosOpenOrder $order): void
    {
        $quantities = $this->quantities($order);

        if ($quantities === []) {
            return;
        }

        foreach ($quantities as $variantId => $quantity) {
            $inventory = $this->inventory($order->branch_id, $variantId);

            if (! $inventory instanceof BranchInventory || $inventory->available() < $quantity) {
                throw ValidationException::withMessages([
                    'items' => 'That item is no longer available.',
                ]);
            }

            $inventory->increment('reserved', $quantity);
        }

        $order->inventory_hold = InventoryHold::Held;
        $order->save();
    }

    public function release(PosOpenOrder $order): void
    {
        $order = $this->lock($order);

        if (! $order instanceof PosOpenOrder || $order->inventory_hold !== InventoryHold::Held) {
            return;
        }

        $this->giveBack($order);
        $order->inventory_hold = InventoryHold::Released;
        $order->save();
    }

    public function commit(PosOpenOrder $order): void
    {
        $order = $this->lock($order);

        if (! $order instanceof PosOpenOrder || $order->inventory_hold !== InventoryHold::Held) {
            return;
        }

        $this->giveBack($order);
        $order->inventory_hold = InventoryHold::Sold;
        $order->save();
    }

    /**
     * Units this order already holds, so a sale of the order can use them.
     *
     * @return array<int, int>
     */
    public function coveredQuantities(?PosOpenOrder $order): array
    {
        if (! $order instanceof PosOpenOrder || $order->inventory_hold !== InventoryHold::Held) {
            return [];
        }

        return $this->quantities($order);
    }

    /**
     * @return array<int, int>
     */
    private function quantities(PosOpenOrder $order): array
    {
        $quantities = [];

        foreach ($order->items ?? [] as $item) {
            if (! is_array($item) || ! ($item['is_product'] ?? false)) {
                continue;
            }

            $notes = $item['notes'] ?? null;

            if (is_string($notes) && strcasecmp($notes, 'Preorder') === 0) {
                continue;
            }

            $variantId = (int) ($item['catalog_item_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId < 1 || $quantity < 1) {
                continue;
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $quantity;
        }

        ksort($quantities);

        return $quantities;
    }

    private function lock(PosOpenOrder $order): ?PosOpenOrder
    {
        $locked = PosOpenOrder::query()->whereKey($order->id)->lockForUpdate()->first();

        return $locked instanceof PosOpenOrder ? $locked : null;
    }

    private function inventory(int $branchId, int $variantId): ?BranchInventory
    {
        $inventory = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('catalog_item_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        return $inventory instanceof BranchInventory ? $inventory : null;
    }

    private function giveBack(PosOpenOrder $order): void
    {
        foreach ($this->quantities($order) as $variantId => $quantity) {
            $inventory = $this->inventory($order->branch_id, $variantId);

            if (! $inventory instanceof BranchInventory) {
                continue;
            }

            $inventory->reserved = max(0, $inventory->reserved - $quantity);
            $inventory->save();
        }
    }
}
