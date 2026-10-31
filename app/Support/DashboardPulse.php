<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Enums\PosCashMovementType;
use App\Models\BranchInventory;
use App\Models\Business;
use App\Models\PosCashMovement;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardPulse
{
    public const COVER_DAYS = 7;

    public const VELOCITY_DAYS = 14;

    /**
     * @return array{
     *     target: ?string,
     *     metrics: list<array{label: string, today: string, last_week: string, change: string, target_gap: ?string}>,
     *     hours: list<array{label: string, total: string, amount: float, receipts: int, slow: bool}>,
     *     refunds: list<array{cashier: string, branch: string, count: int, total: string, flagged: bool}>,
     *     discounts: list<array{cashier: string, total: string, flagged: bool}>,
     *     drawer: list<array{cashier: string, branch: string, cash_in: string, cash_out: string, opens: int, flagged: bool}>,
     *     open_shifts: list<array{branch: string, opened_by: string, opened_at: string, starting_cash: string}>,
     *     variances: list<array{branch: string, closed_by: string, variance: string, direction: string}>
     * }
     */
    public function today(): array
    {
        $today = ReportPeriod::day();
        $lastWeek = ReportPeriod::lastWeekDay();
        $todaySales = $this->sales($today);
        $lastWeekSales = $this->sales($lastWeek);
        $target = Business::current()?->daily_sales_target;
        $targetAmount = $target === null ? null : (float) $target;

        return [
            'target' => $targetAmount === null ? null : Peso::format($targetAmount),
            'metrics' => [
                $this->metric('Revenue', $todaySales['revenue'], $lastWeekSales['revenue'], $targetAmount),
                $this->metric('Transactions', (float) $todaySales['transactions'], (float) $lastWeekSales['transactions'], null, 0),
                $this->metric('Average basket', $todaySales['basket'], $lastWeekSales['basket'], null),
            ],
            'hours' => $this->hours($today),
            'refunds' => $this->refunds($today),
            'discounts' => $this->discounts($today),
            'drawer' => $this->drawer($today),
            'open_shifts' => $this->openShifts(),
            'variances' => $this->variances($today),
        ];
    }

    /**
     * @return array{low: list<array{product: string, variant: ?string, branch: string, quantity: int, status: string}>, cover: list<array{product: string, variant: ?string, branch: string, on_hand: int, sold: int, days: string}>}
     */
    public function stock(): array
    {
        $reports = new ShopReports;

        return [
            'low' => $reports->lowStock(),
            'cover' => $this->daysOfCover(),
        ];
    }

    /**
     * @return array{revenue: float, transactions: int, basket: float}
     */
    private function sales(ReportPeriod $period): array
    {
        $row = PosSale::query()
            ->whereNull('refunded_at')
            ->whereBetween('sold_at', [$period->from, $period->to])
            ->selectRaw('coalesce(sum(total), 0) as revenue')
            ->selectRaw('count(*) as transactions')
            ->toBase()
            ->first();

        $transactions = (int) ($row->transactions ?? 0);
        $revenue = (float) ($row->revenue ?? 0);

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'basket' => $transactions === 0 ? 0.0 : $revenue / $transactions,
        ];
    }

    /**
     * @return array{label: string, today: string, last_week: string, change: string, target_gap: ?string}
     */
    private function metric(string $label, float $today, float $lastWeek, ?float $target, int $decimals = 2): array
    {
        $format = fn (float $amount): string => $decimals === 0
            ? number_format($amount, 0)
            : Peso::format($amount);

        return [
            'label' => $label,
            'today' => $format($today),
            'last_week' => $format($lastWeek),
            'change' => $this->change($today, $lastWeek),
            'target_gap' => $target === null || $label !== 'Revenue' ? null : $this->targetGap($today, $target),
        ];
    }

    private function change(float $today, float $lastWeek): string
    {
        if ($lastWeek == 0.0) {
            return $today > 0 ? 'New' : '0%';
        }

        $percent = (int) round((($today - $lastWeek) / $lastWeek) * 100);

        return ($percent > 0 ? '+' : '').$percent.'%';
    }

    private function targetGap(float $today, float $target): string
    {
        $gap = round($today - $target, 2);

        if (abs($gap) < 0.005) {
            return 'On target';
        }

        return $gap > 0
            ? Peso::format($gap).' above target'
            : Peso::format(abs($gap)).' below target';
    }

    /**
     * @return list<array{label: string, total: string, amount: float, receipts: int, slow: bool}>
     */
    private function hours(ReportPeriod $period): array
    {
        $buckets = collect(range(0, 23))->mapWithKeys(fn (int $hour): array => [$hour => ['amount' => 0.0, 'receipts' => 0]]);

        PosSale::query()
            ->whereNull('refunded_at')
            ->whereBetween('sold_at', [$period->from, $period->to])
            ->get(['sold_at', 'total'])
            ->each(function (PosSale $sale) use ($buckets): void {
                $hour = $sale->sold_at?->timezone(ReportPeriod::ZONE)->hour ?? 0;
                $current = $buckets->get($hour);
                $buckets->put($hour, [
                    'amount' => $current['amount'] + (float) $sale->total,
                    'receipts' => $current['receipts'] + 1,
                ]);
            });

        $currentHour = CarbonImmutable::now(ReportPeriod::ZONE)->hour;
        $firstSaleHour = $buckets->filter(fn (array $bucket): bool => $bucket['receipts'] > 0)->keys()->min();
        $active = $firstSaleHour === null
            ? collect()
            : $buckets->filter(fn (array $bucket, int $hour): bool => $hour >= $firstSaleHour && $hour <= $currentHour);
        $average = $active->isEmpty() ? 0.0 : (float) $active->avg('amount');

        return $buckets->map(function (array $bucket, int $hour) use ($currentHour, $firstSaleHour, $average): array {
            $slow = $firstSaleHour !== null
                && $hour >= $firstSaleHour
                && $hour <= $currentHour
                && $average > 0
                && $bucket['amount'] < ($average * 0.5);

            return [
                'label' => CarbonImmutable::createFromTime($hour, 0, 0, ReportPeriod::ZONE)->format('g A'),
                'total' => Peso::format($bucket['amount']),
                'amount' => round($bucket['amount'], 2),
                'receipts' => $bucket['receipts'],
                'slow' => $slow,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{cashier: string, branch: string, count: int, total: string, flagged: bool}>
     */
    private function refunds(ReportPeriod $period): array
    {
        return PosSale::query()
            ->with('branch:id,name')
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$period->from, $period->to])
            ->get()
            ->groupBy(fn (PosSale $sale): string => ($sale->refunded_by_name ?: 'Unknown').'|'.($sale->branch?->name ?? 'Unknown branch'))
            ->map(function (Collection $sales): array {
                $sale = $sales->first();
                $count = $sales->count();

                return [
                    'cashier' => $sale?->refunded_by_name ?: 'Unknown',
                    'branch' => $sale?->branch?->name ?? 'Unknown branch',
                    'count' => $count,
                    'total' => Peso::format($sales->sum('total')),
                    'flagged' => $count >= 2,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @return list<array{cashier: string, total: string, flagged: bool}>
     */
    private function discounts(ReportPeriod $period): array
    {
        $rows = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->get(['pos_sales.sold_by_name', 'pos_sale_items.unit_price', 'pos_sale_items.quantity', 'pos_sale_items.line_total'])
            ->groupBy(fn (object $row): string => $row->sold_by_name ?: 'Unknown')
            ->map(function (Collection $items, string $cashier): array {
                $discount = $items->sum(fn (object $item): float => max(0, ((float) $item->unit_price * (int) $item->quantity) - (float) $item->line_total));

                return [
                    'cashier' => $cashier,
                    'amount' => round($discount, 2),
                    'total' => Peso::format($discount),
                    'flagged' => false,
                ];
            })
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        $highest = (float) ($rows->max('amount') ?? 0);

        return $rows->map(function (array $row) use ($highest): array {
            $row['flagged'] = $highest > 0 && $row['amount'] === $highest;

            unset($row['amount']);

            return $row;
        })->all();
    }

    /**
     * @return list<array{cashier: string, branch: string, cash_in: string, cash_out: string, opens: int, flagged: bool}>
     */
    private function drawer(ReportPeriod $period): array
    {
        return PosCashMovement::query()
            ->with('shift.branch:id,name')
            ->whereBetween('recorded_at', [$period->from, $period->to])
            ->get()
            ->groupBy(fn (PosCashMovement $movement): string => ($movement->employee_name ?: 'Unknown').'|'.($movement->shift?->branch?->name ?? 'Unknown branch'))
            ->map(function (Collection $movements): array {
                $first = $movements->first();
                $opens = $movements->count();

                return [
                    'cashier' => $first?->employee_name ?: 'Unknown',
                    'branch' => $first?->shift?->branch?->name ?? 'Unknown branch',
                    'cash_in' => Peso::format($movements->where('type', PosCashMovementType::CashIn)->sum('amount')),
                    'cash_out' => Peso::format($movements->where('type', PosCashMovementType::CashOut)->sum('amount')),
                    'opens' => $opens,
                    'flagged' => $opens >= 3,
                ];
            })
            ->sortByDesc('opens')
            ->values()
            ->all();
    }

    /**
     * @return list<array{branch: string, opened_by: string, opened_at: string, starting_cash: string}>
     */
    private function openShifts(): array
    {
        return PosShift::query()
            ->open()
            ->with('branch:id,name')
            ->orderBy('opened_at')
            ->get()
            ->map(fn (PosShift $shift): array => [
                'branch' => $shift->branch?->name ?? 'Unknown branch',
                'opened_by' => $shift->opened_by_name,
                'opened_at' => $shift->opened_at?->timezone(ReportPeriod::ZONE)->format('M j, g:i A') ?? '',
                'starting_cash' => Peso::format($shift->starting_cash),
            ])
            ->all();
    }

    /**
     * @return list<array{branch: string, closed_by: string, variance: string, direction: string}>
     */
    private function variances(ReportPeriod $period): array
    {
        return PosShift::query()
            ->with('branch:id,name')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$period->from, $period->to])
            ->where('variance', '!=', 0)
            ->orderBy('closed_at')
            ->get()
            ->map(function (PosShift $shift): array {
                $variance = (float) $shift->variance;

                return [
                    'branch' => $shift->branch?->name ?? 'Unknown branch',
                    'closed_by' => $shift->closed_by_name ?: 'Unknown',
                    'variance' => ($variance < 0 ? '-' : '').Peso::format(abs($variance)),
                    'direction' => $variance < 0 ? 'Short' : 'Over',
                ];
            })
            ->all();
    }

    /**
     * @return list<array{product: string, variant: ?string, branch: string, on_hand: int, sold: int, days: string}>
     */
    private function daysOfCover(): array
    {
        $from = CarbonImmutable::now(ReportPeriod::ZONE)->subDays(self::VELOCITY_DAYS - 1)->startOfDay()->utc();
        $to = CarbonImmutable::now(ReportPeriod::ZONE)->endOfDay()->utc();

        $sold = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$from, $to])
            ->whereNotNull('pos_sale_items.catalog_item_variant_id')
            ->groupBy('pos_sales.branch_id', 'pos_sale_items.catalog_item_variant_id')
            ->select('pos_sales.branch_id', 'pos_sale_items.catalog_item_variant_id')
            ->selectRaw('sum(pos_sale_items.quantity) as sold')
            ->get()
            ->keyBy(fn (object $row): string => $row->branch_id.'-'.$row->catalog_item_variant_id);

        return BranchInventory::query()
            ->with(['branch:id,name', 'variant:id,catalog_item_id,name', 'variant.catalogItem:id,name,type'])
            ->whereHas('variant.catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product->value))
            ->get()
            ->map(function (BranchInventory $inventory) use ($sold): ?array {
                $soldRow = $sold->get($inventory->branch_id.'-'.$inventory->catalog_item_variant_id);
                $quantitySold = (int) ($soldRow->sold ?? 0);

                if ($quantitySold === 0 || $inventory->variant?->catalogItem?->type?->value !== 'product') {
                    return null;
                }

                $days = ($inventory->quantity / $quantitySold) * self::VELOCITY_DAYS;

                if ($days >= self::COVER_DAYS) {
                    return null;
                }

                $variantName = $inventory->variant?->name;

                return [
                    'product' => $inventory->variant?->catalogItem?->name ?? 'Unknown product',
                    'variant' => $variantName === null || $variantName === 'Default' ? null : $variantName,
                    'branch' => $inventory->branch?->name ?? 'Unknown branch',
                    'on_hand' => $inventory->quantity,
                    'sold' => $quantitySold,
                    'days' => number_format($days, 1),
                ];
            })
            ->filter()
            ->sortBy('days')
            ->values()
            ->all();
    }
}
