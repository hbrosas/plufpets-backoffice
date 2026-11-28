<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Support\StockAdjustmentLines;
use App\Support\TestingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStock
{
    public function __construct(private StockAdjustmentLines $lines) {}

    /**
     * @param  array{
     *     branch_id: int,
     *     scope: string,
     *     brand_id: int|null,
     *     category_id: int|null,
     *     record_as_sale: bool,
     *     lines: list<array{catalog_item_variant_id: int, quantity: int}>
     * }  $attributes
     * @param  array{employee_number?: string, name?: string}  $employee
     */
    public function handle(array $attributes, array $employee): StockAdjustment
    {
        return DB::transaction(function () use ($attributes, $employee): StockAdjustment {
            $branchId = (int) $attributes['branch_id'];
            $scope = (string) $attributes['scope'];
            $brandId = $attributes['brand_id'] === null ? null : (int) $attributes['brand_id'];
            $categoryId = $attributes['category_id'] === null ? null : (int) $attributes['category_id'];
            $recordAsSale = (bool) $attributes['record_as_sale'];
            $submitted = array_values($attributes['lines']);
            $variantIds = array_map(fn (array $line): int => (int) $line['catalog_item_variant_id'], $submitted);
            $variants = $this->lines->variantsFor($variantIds, $branchId);
            $inventories = BranchInventory::query()
                ->where('branch_id', $branchId)
                ->whereIn('catalog_item_variant_id', $variantIds)
                ->orderBy('catalog_item_variant_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('catalog_item_variant_id');

            $errors = [];
            $changes = [];

            foreach ($submitted as $index => $line) {
                $variantId = (int) $line['catalog_item_variant_id'];
                $quantity = (int) $line['quantity'];
                $variant = $variants->get($variantId);
                $prefix = "lines.$index";

                if (! $variant instanceof CatalogItemVariant || ! $this->inScope($variant, $scope, $brandId, $categoryId)) {
                    $errors["$prefix.catalog_item_variant_id"] = $this->scopeMessage($scope);

                    continue;
                }

                $inventory = $inventories->get($variantId);
                $previous = (int) ($inventory->quantity ?? 0);
                $reserved = (int) ($inventory->reserved ?? 0);

                if ($quantity === $previous) {
                    continue;
                }

                if ($quantity < $reserved) {
                    $errors["$prefix.quantity"] = $reserved === 1
                        ? '1 unit is reserved for an open order. The quantity cannot go below that.'
                        : $reserved.' units are reserved for open orders. The quantity cannot go below that.';

                    continue;
                }

                $soldQuantity = $previous - $quantity;
                $countsAsSale = $recordAsSale && $soldQuantity > 0;
                $retail = $this->lines->retailPrice($variant, $branchId);

                if ($countsAsSale && $retail === null) {
                    $errors["$prefix.quantity"] = 'Set a retail price before recording '.$this->productName($variant).' as a sale.';

                    continue;
                }

                $changes[] = [
                    'variant' => $variant,
                    'inventory' => $inventory instanceof BranchInventory ? $inventory : null,
                    'previous' => $previous,
                    'quantity' => $quantity,
                    'sold_quantity' => $countsAsSale ? $soldQuantity : 0,
                    'retail' => $retail,
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($changes === []) {
                throw ValidationException::withMessages([
                    'lines' => 'Change at least one quantity.',
                ]);
            }

            $sale = $this->sale($branchId, $changes, $employee);
            $adjustment = StockAdjustment::query()->create([
                'branch_id' => $branchId,
                'scope' => $scope,
                'brand_id' => $scope === 'brand' ? $brandId : null,
                'category_id' => $scope === 'category' ? $categoryId : null,
                'record_as_sale' => $recordAsSale,
                'pos_sale_id' => $sale?->id,
                'recorded_by_employee_number' => (string) ($employee['employee_number'] ?? ''),
                'recorded_by_name' => filled($employee['name'] ?? null) ? (string) $employee['name'] : 'Staff',
            ]);

            foreach ($changes as $change) {
                $this->setQuantity($branchId, $change['inventory'], $change['variant'], $change['quantity']);

                StockAdjustmentLine::query()->create([
                    'stock_adjustment_id' => $adjustment->id,
                    'catalog_item_variant_id' => $change['variant']->id,
                    'previous_quantity' => $change['previous'],
                    'quantity' => $change['quantity'],
                    'record_as_sale' => $change['sold_quantity'] > 0,
                ]);
            }

            return $adjustment;
        });
    }

    private function inScope(CatalogItemVariant $variant, string $scope, ?int $brandId, ?int $categoryId): bool
    {
        $item = $variant->catalogItem;

        if ($item === null || $item->type !== CatalogItemType::Product) {
            return false;
        }

        if ($scope === 'brand') {
            return (int) $item->brand_id === $brandId;
        }

        if ($scope === 'category') {
            return (int) $item->category_id === $categoryId;
        }

        return $scope === 'all';
    }

    private function scopeMessage(string $scope): string
    {
        return match ($scope) {
            'brand' => 'Choose a product in this brand.',
            'category' => 'Choose a product in this category.',
            default => 'Choose a product.',
        };
    }

    private function productName(CatalogItemVariant $variant): string
    {
        $name = (string) ($variant->catalogItem?->name ?? 'this product');

        if ($variant->name !== '' && strcasecmp($variant->name, 'Default') !== 0) {
            $name .= ' · '.$variant->name;
        }

        return $name;
    }

    /**
     * @param  list<array{variant: CatalogItemVariant, inventory: BranchInventory|null, previous: int, quantity: int, sold_quantity: int, retail: float|null}>  $changes
     * @param  array{employee_number?: string, name?: string}  $employee
     */
    private function sale(int $branchId, array $changes, array $employee): ?PosSale
    {
        $sold = array_values(array_filter($changes, fn (array $change): bool => $change['sold_quantity'] > 0));

        if ($sold === []) {
            return null;
        }

        $lineTotals = [];
        $totalCents = 0;

        foreach ($sold as $change) {
            $unitCents = (int) round(((float) $change['retail']) * 100);
            $lineCents = $unitCents * $change['sold_quantity'];
            $totalCents += $lineCents;
            $lineTotals[] = [$change, $unitCents, $lineCents];
        }

        $total = number_format($totalCents / 100, 2, '.', '');
        $cash = PaymentMethod::query()
            ->where('is_cash', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        $sale = PosSale::query()->create([
            'pos_shift_id' => null,
            'branch_id' => $branchId,
            'number' => $this->nextNumber($branchId),
            'payment_method_id' => $cash?->id,
            'payment_method_name' => $cash?->name ?? 'Cash',
            'is_cash' => true,
            'delivery_fee' => 0,
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
            'hotel_credit' => 0,
            'amount_tendered' => $total,
            'change_due' => 0,
            'sold_by_employee_number' => (string) ($employee['employee_number'] ?? ''),
            'sold_by_name' => filled($employee['name'] ?? null) ? (string) $employee['name'] : 'Staff',
            'sold_at' => now(),
        ]);

        foreach ($lineTotals as [$change, $unitCents, $lineCents]) {
            PosSaleItem::query()->create([
                'pos_sale_id' => $sale->id,
                'catalog_item_variant_id' => $change['variant']->id,
                'product_name' => (string) ($change['variant']->catalogItem?->name ?? 'Product'),
                'variant_name' => (string) $change['variant']->name,
                'sku' => $change['variant']->sku,
                'quantity' => $change['sold_quantity'],
                'unit_price' => number_format($unitCents / 100, 2, '.', ''),
                'line_total' => number_format($lineCents / 100, 2, '.', ''),
                'is_free' => false,
            ]);
        }

        PosSalePayment::query()->create([
            'pos_sale_id' => $sale->id,
            'payment_method_id' => $cash?->id,
            'payment_method_name' => $cash?->name ?? 'Cash',
            'is_cash' => true,
            'amount' => $total,
            'amount_tendered' => $total,
            'change_due' => 0,
        ]);

        return $sale;
    }

    private function nextNumber(int $branchId): string
    {
        $latestNumber = PosSale::query()
            ->withoutGlobalScope(TestingMode::SCOPE)
            ->where('branch_id', $branchId)
            ->whereDate('sold_at', now()->toDateString())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('number');
        $sequence = 1;

        if (is_string($latestNumber) && preg_match('/-(\d+)$/', $latestNumber, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('POS-%s-%04d', now()->format('Ymd'), $sequence);
    }

    private function setQuantity(int $branchId, ?BranchInventory $inventory, CatalogItemVariant $variant, int $quantity): void
    {
        if ($inventory === null) {
            BranchInventory::query()->create([
                'branch_id' => $branchId,
                'catalog_item_variant_id' => $variant->id,
                'quantity' => $quantity,
                'reserved' => 0,
            ]);

            return;
        }

        $inventory->update([
            'quantity' => $quantity,
        ]);
    }
}
