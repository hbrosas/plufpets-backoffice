<?php

namespace App\Support;

use App\Enums\CatalogItemType;
use App\Enums\PurchaseOrderStatus;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Models\PosShift;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockAssignment;
use App\Models\StockConversion;
use App\Models\StockTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExport
{
    public function sales(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->complete()->sales($period);

        return $this->download('Sales report', 'sales', $period, [
            $this->pairs('Summary', [
                'Gross' => $report['summary']['gross'],
                'Discounts' => $report['summary']['discounts'],
                'Net' => $report['summary']['net'],
                'Tax' => $report['summary']['tax'],
                'Collected' => $report['summary']['collected'],
                'Refunds' => $report['summary']['refunds'],
                'Receipts' => $report['summary']['receipts'],
                'Services' => $report['summary']['services'],
            ]),
            $this->receipts($period),
            $this->saleLines($period),
            $this->columns('By day', ['Day', 'Receipts', 'Sales'], $report['days'], ['name', 'receipts', 'total']),
            $this->columns('By hour', ['Hour', 'Receipts', 'Sales'], $report['hours'], ['name', 'receipts', 'total']),
            $this->columns('By category', ['Category', 'Qty', 'Sales'], $report['categories'], ['name', 'quantity', 'total']),
            $this->columns('By item', ['Item', 'Qty', 'Sales'], $report['items'], ['name', 'quantity', 'total']),
            $this->columns('By cashier', ['Cashier', 'Receipts', 'Sales'], $report['cashiers'], ['name', 'receipts', 'total']),
            $this->columns('By branch', ['Branch', 'Receipts', 'Sales'], $report['branches'], ['name', 'receipts', 'total']),
            $this->columns('By payment method', ['Method', 'Receipts', 'Sales'], $report['payments'], ['name', 'receipts', 'total']),
        ]);
    }

    public function commissions(ReportPeriod $period, ?int $employeeId): StreamedResponse
    {
        $report = (new ShopReports)->commissions($period, $employeeId);

        return $this->download('Commissions', 'commissions', $period, [
            $this->pairs('Summary', [
                'Scope' => $employeeId === null ? 'Every employee' : 'Signed-in employee',
                'Total' => $report['total'],
            ]),
            $this->columns('Employees', ['Employee', 'Number', 'Commission'], $report['employees'], ['name', 'employee_number', 'amount']),
            $this->commissionLines($period, $employeeId),
        ]);
    }

    public function profit(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->complete()->profit($period);

        return $this->download('Profit', 'profit', $period, [
            $this->columns('By category', ['Category', 'Revenue', 'Margin', 'Margin %'], $report['categories'], ['name', 'revenue', 'margin', 'margin_rate']),
            $this->columns('Product margins', ['Item', 'Qty', 'Revenue', 'Margin', 'Margin %'], $report['best'], ['name', 'quantity', 'revenue', 'margin', 'margin_rate']),
        ]);
    }

    public function inventory(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->complete()->inventory($period);

        return $this->download('Inventory', 'inventory', $period, [
            $this->pairs('Summary', [
                'On hand' => $report['quantity'],
                'Stock value' => $report['value'],
                'Turnover' => $report['turnover'],
            ]),
            $this->columns('Movement', ['Movement', 'Quantity'], $report['movement'], ['label', 'quantity']),
            $this->columns('Stock on hand', ['Product', 'Variant', 'Branch', 'On hand', 'Value'], $report['items'], ['product', 'variant', 'branch', 'quantity', 'value']),
            $this->assignments($period),
            $this->transfers($period),
            $this->repacks($period),
        ]);
    }

    public function products(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->complete()->products($period);

        return $this->download('Products', 'products', $period, [
            $this->columns('Best sellers', ['Product', 'Qty', 'Sales'], $report['best'], ['name', 'quantity', 'total']),
            $this->columns('Slow movers', ['Product', 'Qty sold', 'Sales'], $report['slow'], ['name', 'quantity', 'total']),
            $this->columns('ABC analysis', ['Product', 'Sales', 'Share', 'Class'], $report['abc'], ['name', 'total', 'share', 'class']),
            $this->columns('Bought together', ['Items', 'Receipts'], $report['together'], ['pair', 'sales']),
        ]);
    }

    public function purchasing(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->purchasing($period);

        return $this->download('Purchasing', 'purchasing', $period, [
            $this->pairs('Summary', [
                'Fill rate' => $report['fill_rate'],
                'Lead time' => $report['lead_time'],
            ]),
            $this->columns('Spend by supplier', ['Supplier', 'Received'], $report['suppliers'], ['name', 'total']),
            $this->receiptLines($period),
            $this->columns('Price changes', ['Product', 'Earlier cost', 'Latest cost'], $report['prices'], ['product', 'previous', 'latest']),
        ]);
    }

    public function staff(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->staff($period);

        return $this->download('Staff', 'staff', $period, [
            $this->columns('Sales by cashier', ['Cashier', 'Number', 'Receipts', 'Sales', 'Refunds', 'Refund rate'], $report['cashiers'], ['name', 'number', 'receipts', 'sales', 'refunds', 'refund_rate']),
            $this->shifts($period),
        ]);
    }

    public function reconciliation(ReportPeriod $period): StreamedResponse
    {
        $report = (new PeriodReports)->reconciliation($period);

        return $this->download('Reconciliation', 'reconciliation', $period, [
            $this->shifts($period),
            $this->columns('Payments', ['Method', 'Receipts', 'Collected'], $report['payments'], ['name', 'receipts', 'total']),
            $this->columns('System stock at 5 or fewer', ['Product', 'Variant', 'Branch', 'System qty', 'Status'], $report['stock'], ['product', 'variant', 'branch', 'quantity', 'status']),
        ]);
    }

    public function payables(): StreamedResponse
    {
        $report = (new PeriodReports)->payables();

        return $this->download('Payables', 'payables', null, [
            $this->pairs('Summary', ['Outstanding' => $report['owed']]),
            $this->columns('Owed to suppliers', ['Supplier', 'Order', 'Outstanding', 'Age'], $report['suppliers'], ['supplier', 'number', 'total', 'age']),
            $this->payableLines(),
            $this->columns('Open tickets', ['Ticket', 'Branch', 'Amount', 'Age'], $report['tickets'], ['name', 'branch', 'total', 'age']),
            $this->ticketLines(),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}>  $sections
     */
    private function download(string $title, string $name, ?ReportPeriod $period, array $sections): StreamedResponse
    {
        $csv = ReportCsv::render($title, $period, $sections);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, ReportCsv::filename($name, $period), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, string|int|float>  $pairs
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function pairs(string $title, array $pairs): array
    {
        $rows = [];

        foreach ($pairs as $label => $value) {
            $rows[] = [$label, $value];
        }

        return [$title, ['Item', 'Value'], $rows];
    }

    /**
     * @param  list<string>  $headers
     * @param  Collection<int, array<string, mixed>>|list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function columns(string $title, array $headers, Collection|array $rows, array $keys): array
    {
        $rows = $rows instanceof Collection ? $rows->all() : $rows;

        return [$title, $headers, array_map(function (array $row) use ($keys): array {
            return array_map(fn (string $key) => $row[$key] ?? '', $keys);
        }, $rows)];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function receipts(ReportPeriod $period): array
    {
        $rows = PosSale::query()
            ->with('branch:id,name')
            ->whereBetween('sold_at', [$period->from, $period->to])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PosSale $sale): array => [
                $sale->number,
                $this->at($sale->sold_at),
                $sale->branch?->name ?? '',
                $sale->sold_by_employee_number,
                $sale->sold_by_name,
                $sale->payment_method_name,
                $sale->is_cash ? 'Yes' : 'No',
                $this->money($sale->subtotal),
                $this->money($sale->delivery_fee),
                $this->money($sale->tax_total),
                $this->money($sale->total),
                $this->money($sale->amount_tendered),
                $this->money($sale->change_due),
                $sale->refunded_at === null ? 'No' : 'Yes',
                $this->at($sale->refunded_at),
                $sale->refunded_by_employee_number,
                $sale->refunded_by_name,
                $sale->refund_reason,
            ])
            ->all();

        return ['Receipts', [
            'Receipt', 'Sold at', 'Branch', 'Cashier number', 'Cashier', 'Payment', 'Cash',
            'Subtotal', 'Delivery fee', 'Tax', 'Total', 'Tendered', 'Change',
            'Refunded', 'Refunded at', 'Refunded by number', 'Refunded by', 'Refund reason',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function saleLines(ReportPeriod $period): array
    {
        $rows = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->leftJoin('branches', 'branches.id', '=', 'pos_sales.branch_id')
            ->leftJoin('catalog_item_variants', 'catalog_item_variants.id', '=', 'pos_sale_items.catalog_item_variant_id')
            ->leftJoin('catalog_items', 'catalog_items.id', '=', 'catalog_item_variants.catalog_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'catalog_items.category_id')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->orderBy('pos_sales.sold_at')
            ->orderBy('pos_sale_items.id')
            ->get([
                'pos_sales.number',
                'pos_sales.sold_at',
                'pos_sales.refunded_at',
                'pos_sales.sold_by_employee_number',
                'pos_sales.sold_by_name',
                'branches.name as branch',
                'pos_sale_items.product_name',
                'pos_sale_items.variant_name',
                'pos_sale_items.sku',
                'pos_sale_items.quantity',
                'pos_sale_items.unit_price',
                'pos_sale_items.line_total',
                'pos_sale_items.notes',
                'catalog_items.name as catalog_name',
                'catalog_items.type',
                'categories.name as category',
            ])
            ->map(function (object $item): array {
                $list = (float) $item->unit_price * (int) $item->quantity;
                $discount = max(0, $list - (float) $item->line_total);

                return [
                    $item->number,
                    $this->at(CarbonImmutable::parse($item->sold_at)),
                    $item->branch,
                    $item->sold_by_employee_number,
                    $item->sold_by_name,
                    $item->catalog_name ?: $item->product_name,
                    $item->variant_name,
                    $item->sku,
                    $item->category ?: 'Uncategorized',
                    $item->type === CatalogItemType::Service->value ? 'Service' : 'Product',
                    (int) $item->quantity,
                    $this->money($item->unit_price),
                    $this->money($list),
                    $this->money($discount),
                    $this->money($item->line_total),
                    $item->refunded_at === null ? 'No' : 'Yes',
                    $item->notes,
                ];
            })
            ->all();

        return ['Sale lines', [
            'Receipt', 'Sold at', 'Branch', 'Cashier number', 'Cashier', 'Product', 'Variant', 'SKU',
            'Category', 'Type', 'Qty', 'Unit price', 'List amount', 'Discount', 'Line total', 'Refunded', 'Notes',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function commissionLines(ReportPeriod $period, ?int $employeeId): array
    {
        $rows = PosSaleItemCommission::query()
            ->payable()
            ->join('pos_sale_items', 'pos_sale_items.id', '=', 'pos_sale_item_commissions.pos_sale_item_id')
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->leftJoin('branches', 'branches.id', '=', 'pos_sales.branch_id')
            ->whereNull('pos_sales.refunded_at')
            ->whereBetween('pos_sales.sold_at', [$period->from, $period->to])
            ->when($employeeId !== null, fn ($query) => $query->where('pos_sale_item_commissions.employee_id', $employeeId))
            ->orderBy('pos_sales.sold_at')
            ->orderBy('pos_sale_item_commissions.id')
            ->get([
                'pos_sales.number',
                'pos_sales.sold_at',
                'branches.name as branch',
                'pos_sale_items.product_name',
                'pos_sale_items.variant_name',
                'pos_sale_item_commissions.employee_number',
                'pos_sale_item_commissions.employee_name',
                'pos_sale_item_commissions.role',
                'pos_sale_item_commissions.rate',
                'pos_sale_item_commissions.pool_size',
                'pos_sale_item_commissions.commissionable_amount',
                'pos_sale_item_commissions.amount',
            ])
            ->map(fn (object $row): array => [
                $row->number,
                $this->at(CarbonImmutable::parse($row->sold_at)),
                $row->branch,
                $row->product_name,
                $row->variant_name,
                $row->employee_number,
                $row->employee_name,
                $row->role === 'assisted' ? 'Assisted' : 'Assigned',
                $this->money($row->rate),
                (int) $row->pool_size,
                $this->money($row->commissionable_amount),
                $this->money($row->amount),
            ])
            ->all();

        return ['Commission lines', [
            'Receipt', 'Sold at', 'Branch', 'Product', 'Variant', 'Employee number', 'Employee',
            'Role', 'Rate', 'Pool', 'Commissionable', 'Amount',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function assignments(ReportPeriod $period): array
    {
        $rows = StockAssignment::query()
            ->with(['branch:id,name', 'purchaseOrderItem.variant.catalogItem'])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->orderBy('created_at')
            ->get()
            ->map(fn (StockAssignment $row): array => [
                $this->at($row->created_at),
                $row->branch?->name ?? '',
                $row->purchaseOrderItem?->variant?->catalogItem?->name ?? '',
                $row->purchaseOrderItem?->variant?->name ?? '',
                $row->quantity,
                $this->money($row->purchaseOrderItem?->unit_cost),
                $this->money($row->selling_price),
            ])
            ->all();

        return ['Stock received', ['Received at', 'Branch', 'Product', 'Variant', 'Qty', 'Unit cost', 'Selling price'], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function transfers(ReportPeriod $period): array
    {
        $rows = StockTransfer::query()
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'variant.catalogItem:id,name'])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->orderBy('created_at')
            ->get()
            ->map(fn (StockTransfer $row): array => [
                $this->at($row->created_at),
                $row->fromBranch?->name ?? '',
                $row->toBranch?->name ?? '',
                $row->variant?->catalogItem?->name ?? '',
                $row->variant?->name ?? '',
                $row->quantity,
                $row->notes,
            ])
            ->all();

        return ['Transfers', ['Transferred at', 'From', 'To', 'Product', 'Variant', 'Qty', 'Notes'], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function repacks(ReportPeriod $period): array
    {
        $rows = StockConversion::query()
            ->with(['branch:id,name', 'fromVariant.catalogItem:id,name', 'toVariant.catalogItem:id,name'])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->orderBy('created_at')
            ->get()
            ->map(fn (StockConversion $row): array => [
                $this->at($row->created_at),
                $row->branch?->name ?? '',
                $row->fromVariant?->catalogItem?->name ?? '',
                $row->from_quantity,
                $row->toVariant?->catalogItem?->name ?? '',
                $row->to_quantity,
                $this->money($row->unit_cost),
                $row->notes,
            ])
            ->all();

        return ['Repacks', ['Repacked at', 'Branch', 'From product', 'From qty', 'To product', 'To qty', 'Unit cost', 'Notes'], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function receiptLines(ReportPeriod $period): array
    {
        $rows = PurchaseOrderReceiptItem::query()
            ->with(['receipt.purchaseOrder.supplier', 'purchaseOrderItem.variant.catalogItem'])
            ->whereHas('receipt', fn ($query) => $query->whereBetween('received_at', [$period->from, $period->to]))
            ->get()
            ->sortBy(fn (PurchaseOrderReceiptItem $item) => $item->receipt?->received_at?->timestamp ?? 0)
            ->map(function (PurchaseOrderReceiptItem $item): array {
                $cost = (float) ($item->purchaseOrderItem?->unit_cost ?? 0);

                return [
                    $this->at($item->receipt?->received_at),
                    $item->receipt?->purchaseOrder?->supplier?->name ?? '',
                    $item->receipt?->purchaseOrder?->number ?? '',
                    $item->receipt?->delivery_receipt_number,
                    $item->receipt?->sales_invoice_number,
                    $item->purchaseOrderItem?->variant?->catalogItem?->name ?? '',
                    $item->purchaseOrderItem?->variant?->name ?? '',
                    $item->quantity,
                    $this->money($cost),
                    $this->money($cost * $item->quantity),
                    $item->receipt?->received_by_name,
                ];
            })
            ->values()
            ->all();

        return ['Receipt lines', [
            'Received at', 'Supplier', 'Purchase order', 'Delivery receipt', 'Sales invoice',
            'Product', 'Variant', 'Qty', 'Unit cost', 'Line cost', 'Received by',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function shifts(ReportPeriod $period): array
    {
        $rows = PosShift::query()
            ->with(['branch:id,name', 'sales'])
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$period->from, $period->to])
            ->orderBy('closed_at')
            ->get()
            ->map(function (PosShift $shift): array {
                $variance = (float) $shift->variance;
                $sales = $shift->sales->whereNull('refunded_at');

                return [
                    $shift->branch?->name ?? '',
                    $shift->opened_by_employee_number,
                    $shift->opened_by_name,
                    $this->at($shift->opened_at),
                    $shift->closed_by_employee_number,
                    $shift->closed_by_name,
                    $this->at($shift->closed_at),
                    $this->money($shift->starting_cash),
                    $this->money($shift->expected_cash),
                    $this->money($shift->counted_cash),
                    $this->money($variance),
                    abs($variance) < 0.005 ? 'Even' : ($variance < 0 ? 'Short' : 'Over'),
                    $this->money($shift->remit_amount),
                    $sales->count(),
                    $this->money($sales->sum('total')),
                ];
            })
            ->all();

        return ['Shifts', [
            'Branch', 'Opened by number', 'Opened by', 'Opened at', 'Closed by number', 'Closed by', 'Closed at',
            'Starting cash', 'Expected cash', 'Counted cash', 'Variance', 'Result', 'Remit', 'Receipts', 'Sales',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function payableLines(): array
    {
        $now = CarbonImmutable::now(ReportPeriod::ZONE);
        $rows = PurchaseOrder::query()
            ->with(['supplier', 'items.variant.catalogItem'])
            ->where('status', PurchaseOrderStatus::Confirmed)
            ->orderBy('ordered_at')
            ->get()
            ->flatMap(function (PurchaseOrder $order) use ($now): Collection {
                $orderedAt = $order->ordered_at ?? $order->created_at;

                return $order->items
                    ->filter(fn (PurchaseOrderItem $item): bool => $item->quantity > $item->received_quantity)
                    ->map(function (PurchaseOrderItem $item) use ($order, $orderedAt, $now): array {
                        $outstanding = $item->quantity - $item->received_quantity;
                        $days = $orderedAt === null ? 0 : (int) $orderedAt->timezone(ReportPeriod::ZONE)->diffInDays($now);

                        return [
                            $order->supplier?->name ?? '',
                            $order->number,
                            $this->at($orderedAt),
                            $item->variant?->catalogItem?->name ?? '',
                            $item->variant?->name ?? '',
                            $item->quantity,
                            $item->received_quantity,
                            $outstanding,
                            $this->money($item->unit_cost),
                            $this->money($outstanding * (float) $item->unit_cost),
                            $this->age($days),
                        ];
                    });
            })
            ->all();

        return ['Outstanding lines', [
            'Supplier', 'Purchase order', 'Ordered at', 'Product', 'Variant',
            'Ordered', 'Received', 'Outstanding qty', 'Unit cost', 'Outstanding amount', 'Age',
        ], $rows];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: list<list<string|int|float|null>>}
     */
    private function ticketLines(): array
    {
        $now = CarbonImmutable::now(ReportPeriod::ZONE);
        $rows = PosOpenOrder::query()
            ->with('branch:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(function (PosOpenOrder $order) use ($now): array {
                $days = (int) $order->created_at?->timezone(ReportPeriod::ZONE)->diffInDays($now);

                return [
                    $order->number,
                    $order->name,
                    $order->branch?->name ?? '',
                    $order->saved_by_employee_number,
                    $order->saved_by_name,
                    $this->at($order->created_at),
                    $this->money($order->subtotal),
                    $this->age($days),
                ];
            })
            ->all();

        return ['Open ticket details', [
            'Number', 'Name', 'Branch', 'Saved by number', 'Saved by', 'Saved at', 'Amount', 'Age',
        ], $rows];
    }

    private function at(mixed $moment): string
    {
        if ($moment === null) {
            return '';
        }

        return CarbonImmutable::parse($moment)->timezone(ReportPeriod::ZONE)->format('Y-m-d H:i');
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function age(int $days): string
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
