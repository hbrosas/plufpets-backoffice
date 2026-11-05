<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Enums\CommissionRole;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShopReports
{
    public const LOW_STOCK_AT = 5;

    /**
     * @return array{
     *     periods: list<array{key: string, label: string, total: string, receipts: int}>,
     *     branches: list<array{name: string, today: string, month: string, quarter: string}>,
     *     services: array{today: int, month: int, quarter: int},
     *     receipts: array{sold: int, refunded: int},
     *     top_products: list<array{name: string, quantity: int, total: string}>
     * }
     */
    public function salesSnapshot(): array
    {
        $today = ReportPeriod::today();
        $month = ReportPeriod::month();
        $quarter = ReportPeriod::quarter();
        $todaySales = $this->branchSales($today);
        $monthSales = $this->branchSales($month);
        $quarterSales = $this->branchSales($quarter);

        $names = collect([$todaySales, $monthSales, $quarterSales])
            ->flatMap(fn (array $period): array => array_column($period['branches'], 'name'))
            ->unique()
            ->values();

        return [
            'periods' => [
                $this->periodSummary('today', $today, $todaySales),
                $this->periodSummary('month', $month, $monthSales),
                $this->periodSummary('quarter', $quarter, $quarterSales),
            ],
            'branches' => $names->map(function (string $name) use ($todaySales, $monthSales, $quarterSales): array {
                return [
                    'name' => $name,
                    'today' => $this->branchTotal($todaySales, $name),
                    'month' => $this->branchTotal($monthSales, $name),
                    'quarter' => $this->branchTotal($quarterSales, $name),
                ];
            })->all(),
            'services' => [
                'today' => $this->serviceCount($today),
                'month' => $this->serviceCount($month),
                'quarter' => $this->serviceCount($quarter),
            ],
            'receipts' => $this->receiptCounts($month),
            'top_products' => $this->topProducts($month),
        ];
    }

    /**
     * @return array{label: string, receipts: int, total: string, branches: list<array{name: string, receipts: int, total: string}>}
     */
    public function branchSales(ReportPeriod $period): array
    {
        $rows = PosSale::query()
            ->whereNull('refunded_at')
            ->whereBetween('sold_at', [$period->from, $period->to])
            ->toBase()
            ->select('branch_id')
            ->selectRaw('count(*) as receipts')
            ->selectRaw('coalesce(sum(total), 0) as total')
            ->groupBy('branch_id')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->branch_id);

        $branches = Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(function (Branch $branch) use ($rows): array {
                $row = $rows->get($branch->id);

                return [
                    'name' => $branch->name,
                    'receipts' => (int) ($row->receipts ?? 0),
                    'total' => Peso::format($row->total ?? 0),
                ];
            });

        return [
            'label' => $period->label,
            'receipts' => (int) $rows->sum('receipts'),
            'total' => Peso::format($rows->sum('total')),
            'branches' => $branches->all(),
        ];
    }

    /**
     * @return array{sold: int, refunded: int}
     */
    public function receiptCounts(ReportPeriod $period): array
    {
        $sales = PosSale::query()->whereBetween('sold_at', [$period->from, $period->to]);

        return [
            'sold' => (clone $sales)->whereNull('refunded_at')->count(),
            'refunded' => (clone $sales)->whereNotNull('refunded_at')->count(),
        ];
    }

    public function serviceCount(ReportPeriod $period): int
    {
        return (int) $this->soldItems($period)
            ->where('catalog_items.type', CatalogItemType::Service->value)
            ->sum('pos_sale_items.quantity');
    }

    /**
     * @return list<array{name: string, quantity: int, total: string}>
     */
    public function topProducts(ReportPeriod $period, int $limit = 10): array
    {
        return $this->soldItems($period)
            ->where('catalog_items.type', CatalogItemType::Product->value)
            ->groupBy('catalog_items.id', 'catalog_items.name')
            ->select('catalog_items.name')
            ->selectRaw('sum(pos_sale_items.quantity) as quantity')
            ->selectRaw('coalesce(sum(pos_sale_items.line_total), 0) as total')
            ->orderByDesc('quantity')
            ->orderBy('catalog_items.name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'total' => Peso::format($row->total),
            ])
            ->all();
    }

    /**
     * @return array{label: string, total: string, employees: list<array{name: string, employee_number: string, amount: string}>}
     */
    public function commissions(ReportPeriod $period, ?int $employeeId = null): array
    {
        $rows = PosSaleItemCommission::query()
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_item_commissions.pos_sale_item_id')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->when($employeeId !== null, fn ($query) => $query->where('pos_sale_item_commissions.employee_id', $employeeId))
            ->groupBy(
                'pos_sale_item_commissions.employee_id',
                'pos_sale_item_commissions.employee_number',
                'pos_sale_item_commissions.employee_name',
            )
            ->select([
                'pos_sale_item_commissions.employee_number',
                'pos_sale_item_commissions.employee_name',
            ])
            ->selectRaw('coalesce(sum(pos_sale_item_commissions.amount), 0) as total')
            ->get()
            ->sort(function (object $left, object $right): int {
                $byTotal = (float) $right->total <=> (float) $left->total;

                if ($byTotal !== 0) {
                    return $byTotal;
                }

                return strcasecmp((string) $left->employee_name, (string) $right->employee_name);
            })
            ->values();

        return [
            'label' => $period->label,
            'total' => Peso::format($rows->sum('total')),
            'employees' => $rows->map(fn (object $row): array => [
                'name' => $row->employee_name,
                'employee_number' => (string) $row->employee_number,
                'amount' => Peso::format($row->total),
            ])->all(),
        ];
    }

    /**
     * @return list<array{sold_at: string, number: string, item: string, role: string, amount: string}>
     */
    public function commissionLines(ReportPeriod $period, int $employeeId): array
    {
        return PosSaleItemCommission::query()
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_item_commissions.pos_sale_item_id')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->where('pos_sale_item_commissions.employee_id', $employeeId)
            ->orderByDesc('pos_sales.sold_at')
            ->orderByDesc('pos_sale_item_commissions.id')
            ->limit(100)
            ->get([
                'pos_sales.number as sale_number',
                'pos_sales.sold_at',
                'pos_sale_items.product_name',
                'pos_sale_items.variant_name',
                'pos_sale_item_commissions.role',
                'pos_sale_item_commissions.amount',
            ])
            ->map(function (object $row): array {
                $variant = trim((string) $row->variant_name);
                $item = (string) $row->product_name;

                if ($variant !== '' && strcasecmp($variant, 'Default') !== 0) {
                    $item .= ' · '.$variant;
                }

                $role = $row->role instanceof CommissionRole
                    ? $row->role->value
                    : (string) $row->role;

                return [
                    'sold_at' => Carbon::parse($row->sold_at)->timezone('Asia/Manila')->format('M j, g:i A'),
                    'number' => (string) $row->sale_number,
                    'item' => $item,
                    'role' => ucfirst($role),
                    'amount' => Peso::format($row->amount),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{product: string, variant: ?string, branch: string, quantity: int, status: string}>
     */
    public function lowStock(): array
    {
        return BranchInventory::query()
            ->with([
                'branch:id,name',
                'variant:id,catalog_item_id,name',
                'variant.catalogItem:id,name,type',
            ])
            ->where('quantity', '<=', self::LOW_STOCK_AT)
            ->whereHas('variant.catalogItem', fn ($query) => $query->where('type', CatalogItemType::Product->value))
            ->orderBy('quantity')
            ->orderBy('id')
            ->get()
            ->map(function (BranchInventory $inventory): array {
                $variantName = $inventory->variant?->name;

                return [
                    'product' => $inventory->variant?->catalogItem?->name ?? 'Unknown product',
                    'variant' => $variantName === null || $variantName === 'Default' ? null : $variantName,
                    'branch' => $inventory->branch?->name ?? 'Unknown branch',
                    'quantity' => $inventory->quantity,
                    'status' => $inventory->quantity === 0 ? 'Out of stock' : 'Low stock',
                ];
            })
            ->sort(function (array $left, array $right): int {
                $byQuantity = $left['quantity'] <=> $right['quantity'];

                if ($byQuantity !== 0) {
                    return $byQuantity;
                }

                return strcasecmp($left['product'], $right['product']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{label: string, receipts: int, total: string, branches: list<array{name: string, receipts: int, total: string}>}  $sales
     * @return array{key: string, label: string, total: string, receipts: int}
     */
    private function periodSummary(string $key, ReportPeriod $period, array $sales): array
    {
        return [
            'key' => $key,
            'label' => $period->label,
            'total' => $sales['total'],
            'receipts' => $sales['receipts'],
        ];
    }

    /**
     * @param  array{branches: list<array{name: string, total: string}>}  $sales
     */
    private function branchTotal(array $sales, string $name): string
    {
        $match = Collection::make($sales['branches'])->firstWhere('name', $name);

        return $match['total'] ?? Peso::format(0);
    }

    /**
     * @return Builder<PosSaleItem>
     */
    private function soldItems(ReportPeriod $period): Builder
    {
        return PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->join('catalog_item_variants', 'catalog_item_variants.id', '=', 'pos_sale_items.catalog_item_variant_id')
            ->join('catalog_items', 'catalog_items.id', '=', 'catalog_item_variants.catalog_item_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to]);
    }
}
