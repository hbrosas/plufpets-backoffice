<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\CatalogItemVariant;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosShift;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockAssignment;
use App\Models\StockConversion;
use App\Models\StockTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PeriodReports
{
    private bool $complete = false;

    public function complete(): self
    {
        $this->complete = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function sales(ReportPeriod $period): array
    {
        $sales = $this->periodSales($period);
        $sold = $sales->whereNull('refunded_at');
        $items = $this->soldItems($period);

        $gross = $items->sum(fn (object $item): float => (float) $item->unit_price * (int) $item->quantity);
        $discounts = $items->sum(fn (object $item): float => max(0, ((float) $item->unit_price * (int) $item->quantity) - (float) $item->line_total));

        return [
            'summary' => [
                'gross' => Peso::format($gross),
                'discounts' => Peso::format($discounts),
                'net' => Peso::format($gross - $discounts),
                'tax' => Peso::format($sold->sum('tax_total')),
                'collected' => Peso::format($sold->sum('total')),
                'refunds' => Peso::format($sales->whereNotNull('refunded_at')->sum('total')),
                'receipts' => $sold->count(),
                'services' => (int) $items->where('type', CatalogItemType::Service->value)->sum('quantity'),
            ],
            'days' => $this->buckets($sold, fn (PosSale $sale): string => $sale->sold_at?->timezone(ReportPeriod::ZONE)->format('M j') ?? ''),
            'hours' => $this->buckets($sold, fn (PosSale $sale): string => $sale->sold_at?->timezone(ReportPeriod::ZONE)->format('g A') ?? ''),
            'categories' => $this->itemGroups($items, 'category'),
            'items' => $this->cap($this->itemGroups($items, 'product'), 50)->all(),
            'cashiers' => $this->saleGroups($sold, 'sold_by_name'),
            'branches' => $this->branchRows($sold),
            'payments' => $this->saleGroups($sold, 'payment_method_name'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profit(ReportPeriod $period): array
    {
        $costs = $this->latestCosts();
        $rows = $this->soldItems($period)
            ->where('type', CatalogItemType::Product->value)
            ->groupBy('product')
            ->map(function (Collection $items, string $product) use ($costs): array {
                $first = $items->first();
                $revenue = (float) $items->sum('line_total');
                $quantity = (int) $items->sum('quantity');
                $cost = $costs->get($first->catalog_item_variant_id);
                $costTotal = $cost === null ? null : round((float) $cost * $quantity, 2);
                $margin = $costTotal === null ? null : round($revenue - $costTotal, 2);

                return [
                    'product' => $product,
                    'category' => $first->category,
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'cost' => $costTotal,
                    'margin' => $margin,
                    'margin_rate' => $revenue > 0 && $margin !== null ? round(($margin / $revenue) * 100, 1) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['margin'] !== null)
            ->values();

        $byCategory = $rows->groupBy('category')->map(function (Collection $items, string $category): array {
            $revenue = (float) $items->sum('revenue');
            $margin = (float) $items->sum('margin');

            return [
                'name' => $category,
                'revenue' => Peso::format($revenue),
                'margin' => Peso::format($margin),
                'margin_rate' => $revenue > 0 ? number_format(($margin / $revenue) * 100, 1).'%' : '0%',
            ];
        })->values()->all();

        $ranked = $rows->sortByDesc('margin_rate')->values();

        return [
            'categories' => $byCategory,
            'best' => $this->marginRows($this->cap($ranked, 10)),
            'worst' => $this->marginRows($this->cap($ranked->sortBy('margin_rate')->values(), 10)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory(ReportPeriod $period): array
    {
        $costs = $this->latestCosts();
        $onHand = BranchInventory::query()
            ->with(['branch:id,name', 'variant:id,catalog_item_id,name,price', 'variant.catalogItem:id,name,type'])
            ->whereHas('variant.catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product->value))
            ->get();

        $value = 0.0;
        $rows = $onHand->map(function (BranchInventory $inventory) use ($costs, &$value): array {
            $cost = $costs->get($inventory->catalog_item_variant_id);
            $unit = $cost ?? (float) ($inventory->variant?->price ?? 0);
            $line = round($unit * $inventory->quantity, 2);
            $value += $line;
            $variantName = $inventory->variant?->name;

            return [
                'product' => $inventory->variant?->catalogItem?->name ?? 'Unknown product',
                'variant' => $variantName === null || $variantName === 'Default' ? null : $variantName,
                'branch' => $inventory->branch?->name ?? 'Unknown branch',
                'quantity' => $inventory->quantity,
                'value' => Peso::format($line),
            ];
        })->sortBy(['product', 'branch'])->values();

        $sold = (int) $this->soldItems($period)->where('type', CatalogItemType::Product->value)->sum('quantity');
        $stock = (int) $onHand->sum('quantity');

        return [
            'value' => Peso::format($value),
            'quantity' => $stock,
            'turnover' => $stock === 0 ? '0' : number_format($sold / $stock, 2),
            'movement' => [
                ['label' => 'Received', 'quantity' => (int) StockAssignment::query()->whereBetween('created_at', [$period->from, $period->to])->sum('quantity')],
                ['label' => 'Sold', 'quantity' => $sold],
                ['label' => 'Transferred', 'quantity' => (int) StockTransfer::query()->whereBetween('created_at', [$period->from, $period->to])->sum('quantity')],
                ['label' => 'Repacked out', 'quantity' => (int) StockConversion::query()->whereBetween('created_at', [$period->from, $period->to])->sum('from_quantity')],
                ['label' => 'Repacked in', 'quantity' => (int) StockConversion::query()->whereBetween('created_at', [$period->from, $period->to])->sum('to_quantity')],
            ],
            'items' => $this->cap($rows, 100)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function products(ReportPeriod $period): array
    {
        $items = $this->soldItems($period)->where('type', CatalogItemType::Product->value);
        $byProduct = $items->groupBy('product')->map(function (Collection $lines, string $product): array {
            return [
                'name' => $product,
                'quantity' => (int) $lines->sum('quantity'),
                'total' => (float) $lines->sum('line_total'),
            ];
        })->sortByDesc('quantity')->values();

        $revenue = (float) $byProduct->sum('total');
        $running = 0.0;
        $abc = $byProduct->sortByDesc('total')->values()->map(function (array $row) use ($revenue, &$running): array {
            $previous = $revenue > 0 ? $running / $revenue : 1;
            $running += $row['total'];
            $class = $previous < 0.8 ? 'A' : ($previous < 0.95 ? 'B' : 'C');

            return [
                'name' => $row['name'],
                'total' => Peso::format($row['total']),
                'share' => $revenue > 0 ? number_format(($row['total'] / $revenue) * 100, 1).'%' : '0%',
                'class' => $class,
            ];
        })->values();

        $soldNames = $byProduct->pluck('name');
        $slow = CatalogItemVariant::query()
            ->with('catalogItem:id,name,type')
            ->whereHas('catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product->value))
            ->whereHas('inventories', fn ($query) => $query->where('quantity', '>', 0))
            ->get()
            ->filter(fn (CatalogItemVariant $variant): bool => ! $soldNames->contains($variant->catalogItem?->name))
            ->map(fn (CatalogItemVariant $variant): array => [
                'name' => $variant->catalogItem?->name ?? 'Unknown product',
                'quantity' => 0,
                'total' => Peso::format(0),
            ])
            ->unique('name')
            ->when(! $this->complete, fn (Collection $rows): Collection => $rows->take(15))
            ->values()
            ->all();

        return [
            'best' => $this->cap($byProduct, 10)->map(fn (array $row): array => [
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'total' => Peso::format($row['total']),
            ])->all(),
            'slow' => $slow,
            'abc' => $abc->all(),
            'together' => $this->boughtTogether($items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchasing(ReportPeriod $period): array
    {
        $receipts = PurchaseOrderReceiptItem::query()
            ->with(['receipt.purchaseOrder.supplier', 'purchaseOrderItem'])
            ->whereHas('receipt', fn ($query) => $query->whereBetween('received_at', [$period->from, $period->to]))
            ->get();

        $spend = $receipts->groupBy(fn (PurchaseOrderReceiptItem $item): string => $item->receipt?->purchaseOrder?->supplier?->name ?? 'Unknown supplier')
            ->map(function (Collection $items, string $supplier): array {
                $amount = $items->sum(fn (PurchaseOrderReceiptItem $item): float => $item->quantity * (float) ($item->purchaseOrderItem?->unit_cost ?? 0));

                return ['name' => $supplier, 'total' => Peso::format($amount), 'amount' => $amount];
            })
            ->sortByDesc('amount')
            ->map(fn (array $row): array => ['name' => $row['name'], 'total' => $row['total']])
            ->values()
            ->all();

        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items', 'receipts'])
            ->where('status', PurchaseOrderStatus::Confirmed)
            ->where(function ($query) use ($period): void {
                $query->whereBetween('ordered_at', [$period->from, $period->to])
                    ->orWhereHas('receipts', fn ($receipts) => $receipts->whereBetween('received_at', [$period->from, $period->to]));
            })
            ->get();

        $ordered = (int) $orders->sum(fn (PurchaseOrder $order): int => $order->orderedQuantity());
        $received = (int) $orders->sum(fn (PurchaseOrder $order): int => $order->receivedQuantity());
        $leadDays = $orders->map(function (PurchaseOrder $order): ?float {
            $receivedAt = $order->receipts->sortBy('received_at')->first()?->received_at;
            if ($order->ordered_at === null || $receivedAt === null) {
                return null;
            }

            return $order->ordered_at->diffInDays($receivedAt);
        })->filter(fn (?float $days): bool => $days !== null);

        return [
            'suppliers' => $spend,
            'fill_rate' => $ordered === 0 ? '0%' : number_format(($received / $ordered) * 100, 1).'%',
            'lead_time' => $leadDays->isEmpty() ? 'No receipts' : number_format((float) $leadDays->avg(), 1).' days',
            'prices' => $this->priceChanges(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staff(ReportPeriod $period): array
    {
        $sales = $this->periodSales($period);
        $cashiers = $sales->groupBy(fn (PosSale $sale): string => $sale->sold_by_employee_number.'|'.$sale->sold_by_name)
            ->map(function (Collection $rows): array {
                $sold = $rows->whereNull('refunded_at');
                $refunded = $rows->whereNotNull('refunded_at');
                $receipts = $rows->count();

                return [
                    'name' => $rows->first()?->sold_by_name ?: 'Unknown',
                    'number' => (string) ($rows->first()?->sold_by_employee_number ?? ''),
                    'sales' => Peso::format($sold->sum('total')),
                    'receipts' => $sold->count(),
                    'refunds' => $refunded->count(),
                    'refund_rate' => $receipts === 0 ? '0%' : number_format(($refunded->count() / $receipts) * 100, 1).'%',
                ];
            })
            ->sortByDesc('receipts')
            ->values()
            ->all();

        $variances = PosShift::query()
            ->with('branch:id,name')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$period->from, $period->to])
            ->orderByDesc('closed_at')
            ->get()
            ->map(function (PosShift $shift): array {
                $variance = (float) $shift->variance;

                return [
                    'branch' => $shift->branch?->name ?? 'Unknown branch',
                    'closed_by' => $shift->closed_by_name ?: $shift->opened_by_name,
                    'variance' => ($variance < 0 ? '-' : '').Peso::format(abs($variance)),
                    'direction' => abs($variance) < 0.005 ? 'Even' : ($variance < 0 ? 'Short' : 'Over'),
                ];
            })
            ->all();

        return [
            'cashiers' => $cashiers,
            'variances' => $variances,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconciliation(ReportPeriod $period): array
    {
        $shifts = PosShift::query()
            ->with(['branch:id,name', 'sales'])
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$period->from, $period->to])
            ->orderBy('closed_at')
            ->get()
            ->map(function (PosShift $shift): array {
                $sales = $shift->sales->whereNull('refunded_at');
                $variance = (float) $shift->variance;

                return [
                    'branch' => $shift->branch?->name ?? 'Unknown branch',
                    'expected' => Peso::format($shift->expected_cash),
                    'counted' => Peso::format($shift->counted_cash),
                    'variance' => ($variance < 0 ? '-' : '').Peso::format(abs($variance)),
                    'direction' => abs($variance) < 0.005 ? 'Even' : ($variance < 0 ? 'Short' : 'Over'),
                    'sales' => Peso::format($sales->sum('total')),
                ];
            });

        $payments = $this->saleGroups($this->periodSales($period)->whereNull('refunded_at'), 'payment_method_name');

        return [
            'shifts' => $shifts->all(),
            'payments' => $payments,
            'stock' => (new ShopReports)->lowStock(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payables(): array
    {
        $now = CarbonImmutable::now(ReportPeriod::ZONE);
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->where('status', PurchaseOrderStatus::Confirmed)
            ->get()
            ->map(function (PurchaseOrder $order) use ($now): ?array {
                $outstanding = $order->items->sum(fn (PurchaseOrderItem $item): float => max(0, $item->quantity - $item->received_quantity) * (float) $item->unit_cost);

                if ($outstanding <= 0) {
                    return null;
                }

                $orderedAt = $order->ordered_at?->timezone(ReportPeriod::ZONE) ?? $order->created_at?->timezone(ReportPeriod::ZONE);
                $days = $orderedAt === null ? 0 : (int) $orderedAt->diffInDays($now);

                return [
                    'supplier' => $order->supplier?->name ?? 'Unknown supplier',
                    'number' => $order->number,
                    'amount' => round($outstanding, 2),
                    'total' => Peso::format($outstanding),
                    'age' => $this->ageBucket($days),
                ];
            })
            ->filter()
            ->values();

        $tickets = PosOpenOrder::query()->with('branch:id,name')->get()->map(function (PosOpenOrder $order) use ($now): array {
            $days = (int) $order->created_at?->timezone(ReportPeriod::ZONE)->diffInDays($now);

            return [
                'name' => $order->name ?: $order->number,
                'branch' => $order->branch?->name ?? 'Unknown branch',
                'total' => Peso::format($order->subtotal),
                'age' => $this->ageBucket($days),
            ];
        })->values();

        return [
            'owed' => Peso::format($orders->sum('amount')),
            'suppliers' => $orders->map(fn (array $row): array => [
                'supplier' => $row['supplier'],
                'number' => $row['number'],
                'total' => $row['total'],
                'age' => $row['age'],
            ])->all(),
            'tickets' => $tickets->all(),
        ];
    }

    /**
     * @return Collection<int, PosSale>
     */
    private function periodSales(ReportPeriod $period): Collection
    {
        return PosSale::query()
            ->with('branch:id,name')
            ->whereBetween('sold_at', [$period->from, $period->to])
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function soldItems(ReportPeriod $period): Collection
    {
        return PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->leftJoin('catalog_item_variants', 'catalog_item_variants.id', '=', 'pos_sale_items.catalog_item_variant_id')
            ->leftJoin('catalog_items', 'catalog_items.id', '=', 'catalog_item_variants.catalog_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'catalog_items.category_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->get([
                'pos_sale_items.pos_sale_id',
                'pos_sale_items.catalog_item_variant_id',
                'pos_sale_items.product_name as product',
                'pos_sale_items.quantity',
                'pos_sale_items.unit_price',
                'pos_sale_items.line_total',
                'catalog_items.name as catalog_name',
                'catalog_items.type',
                'categories.name as category',
            ])
            ->each(function (object $item): void {
                $item->category = $item->category ?: 'Uncategorized';
                $item->product = $item->catalog_name ?: ($item->product ?: 'Unknown product');
            });
    }

    /**
     * @param  Collection<int, PosSale>  $sales
     * @return list<array{name: string, receipts: int, total: string}>
     */
    private function buckets(Collection $sales, callable $label): array
    {
        return $sales->groupBy($label)
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'receipts' => $rows->count(),
                'total' => Peso::format($rows->sum('total')),
                'amount' => (float) $rows->sum('total'),
            ])
            ->sortByDesc('amount')
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'receipts' => $row['receipts'],
                'total' => $row['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $items
     * @return Collection<int, array{name: string, quantity: int, total: string}>
     */
    private function itemGroups(Collection $items, string $field): Collection
    {
        return $items->groupBy($field)
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'quantity' => (int) $rows->sum('quantity'),
                'amount' => (float) $rows->sum('line_total'),
                'total' => Peso::format($rows->sum('line_total')),
            ])
            ->sortByDesc('amount')
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'total' => $row['total'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, PosSale>  $sales
     * @return list<array{name: string, receipts: int, total: string}>
     */
    private function saleGroups(Collection $sales, string $field): array
    {
        return $sales->groupBy(fn (PosSale $sale): string => (string) ($sale->{$field} ?: 'Unknown'))
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'receipts' => $rows->count(),
                'amount' => (float) $rows->sum('total'),
                'total' => Peso::format($rows->sum('total')),
            ])
            ->sortByDesc('amount')
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'receipts' => $row['receipts'],
                'total' => $row['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PosSale>  $sales
     * @return list<array{name: string, receipts: int, total: string}>
     */
    private function branchRows(Collection $sales): array
    {
        $totals = $sales->groupBy(fn (PosSale $sale): int => (int) $sale->branch_id);

        return Branch::query()->orderBy('name')->get(['id', 'name'])->map(function (Branch $branch) use ($totals): array {
            $rows = $totals->get($branch->id, collect());

            return [
                'name' => $branch->name,
                'receipts' => $rows->count(),
                'total' => Peso::format($rows->sum('total')),
            ];
        })->all();
    }

    /**
     * @return Collection<int|string, float>
     */
    private function latestCosts(): Collection
    {
        return PurchaseOrderItem::query()
            ->orderByDesc('id')
            ->get(['catalog_item_variant_id', 'unit_cost'])
            ->unique('catalog_item_variant_id')
            ->mapWithKeys(fn (PurchaseOrderItem $item): array => [$item->catalog_item_variant_id => (float) $item->unit_cost]);
    }

    /**
     * @param  Collection<int, array{product: string, quantity: int, revenue: float, margin: ?float, margin_rate: ?float}>  $rows
     * @return list<array{name: string, quantity: int, revenue: string, margin: string, margin_rate: string}>
     */
    private function marginRows(Collection $rows): array
    {
        return $rows->map(fn (array $row): array => [
            'name' => $row['product'],
            'quantity' => $row['quantity'],
            'revenue' => Peso::format($row['revenue']),
            'margin' => Peso::format($row['margin'] ?? 0),
            'margin_rate' => number_format((float) $row['margin_rate'], 1).'%',
        ])->all();
    }

    /**
     * @param  Collection<int, object>  $items
     * @return list<array{pair: string, sales: int}>
     */
    private function boughtTogether(Collection $items): array
    {
        $pairs = [];

        foreach ($items->groupBy('pos_sale_id') as $lines) {
            $names = $lines->pluck('product')->unique()->sort()->values();

            for ($left = 0; $left < $names->count(); $left++) {
                for ($right = $left + 1; $right < $names->count(); $right++) {
                    $key = $names[$left].' + '.$names[$right];
                    $pairs[$key] = ($pairs[$key] ?? 0) + 1;
                }
            }
        }

        return collect($pairs)
            ->map(fn (int $sales, string $pair): array => ['pair' => $pair, 'sales' => $sales])
            ->sortByDesc('sales')
            ->when(! $this->complete, fn (Collection $rows): Collection => $rows->take(10))
            ->values()
            ->all();
    }

    /**
     * @return list<array{product: string, previous: string, latest: string}>
     */
    private function priceChanges(): array
    {
        return PurchaseOrderReceiptItem::query()
            ->with(['receipt', 'purchaseOrderItem.variant.catalogItem'])
            ->get()
            ->sortBy(fn (PurchaseOrderReceiptItem $item) => $item->receipt?->received_at?->timestamp ?? 0)
            ->groupBy(fn (PurchaseOrderReceiptItem $item): int => (int) $item->purchaseOrderItem?->catalog_item_variant_id)
            ->map(function (Collection $items): ?array {
                $costs = $items->map(fn (PurchaseOrderReceiptItem $item): float => (float) ($item->purchaseOrderItem?->unit_cost ?? 0))->unique()->values();

                if ($costs->count() < 2) {
                    return null;
                }

                $item = $items->last()?->purchaseOrderItem;
                $name = $item?->variant?->catalogItem?->name ?? 'Unknown product';

                return [
                    'product' => $name,
                    'previous' => Peso::format($costs->first()),
                    'latest' => Peso::format($costs->last()),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function cap(Collection $rows, int $limit): Collection
    {
        $rows = $rows->values();

        return $this->complete ? $rows : $rows->take($limit);
    }

    private function ageBucket(int $days): string
    {
        if ($days <= 30) {
            return '0–30 days';
        }

        if ($days <= 60) {
            return '31–60 days';
        }

        return '61+ days';
    }
}
