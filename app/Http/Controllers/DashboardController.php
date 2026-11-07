<?php

namespace App\Http\Controllers;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Enums\PosCashMovementType;
use App\Enums\ShopOrderStatus;
use App\Http\Requests\CommissionPeriodRequest;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\PayrollFiling;
use App\Models\PosCashMovement;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Support\DashboardPulse;
use App\Support\EmployeeSession;
use App\Support\Peso;
use App\Support\ReportPeriod;
use App\Support\ShopReports;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CommissionPeriodRequest $request, ShopReports $reports, DashboardPulse $pulse): Response
    {
        $canSeeSales = EmployeeSession::allows('sales.view');
        $canSeeAllCommissions = $canSeeSales || EmployeeSession::allows('staff.manage');
        $employee = EmployeeSession::current();
        $employeeId = $employee['id'] ?? null;
        $isEmployeeDashboard = ! $canSeeSales
            && ! $canSeeAllCommissions
            && ! EmployeeSession::allows('inventory.view')
            && $employeeId !== null;

        return Inertia::render('Dashboard', [
            'pulse' => $canSeeSales ? $pulse->today() : null,
            'commissions' => $this->commissions($request, $reports, $canSeeAllCommissions, $employeeId),
            'stock' => EmployeeSession::allows('inventory.view') ? $pulse->stock() : null,
            'branchOrders' => $isEmployeeDashboard ? [] : $this->branchOrders(),
            'attendance' => $isEmployeeDashboard ? $this->attendance($request->period(), $employeeId) : null,
            'cashAdvances' => $isEmployeeDashboard ? $this->cashAdvances($employeeId) : null,
            'schedule' => EmployeeSession::allows('staff.manage') ? $this->schedule($request) : null,
        ]);
    }

    /**
     * @return array{leaves: int, overnights: int}
     */
    private function attendance(ReportPeriod $period, int $employeeId): array
    {
        $from = $period->from->timezone(ReportPeriod::ZONE)->startOfDay();
        $to = $period->to->timezone(ReportPeriod::ZONE)->startOfDay();
        $filings = PayrollFiling::query()
            ->where('employee_id', $employeeId)
            ->where('status', PayrollFilingStatus::Approved)
            ->where(function ($query) use ($from, $to): void {
                $query->where(function ($leave) use ($from, $to): void {
                    $leave->where('type', PayrollFilingType::Leave)
                        ->whereDate('starts_on', '<=', $to->toDateString())
                        ->whereDate('ends_on', '>=', $from->toDateString());
                })->orWhere(function ($overnight) use ($from, $to): void {
                    $overnight->where('type', PayrollFilingType::Overnight)
                        ->whereDate('starts_on', '>=', $from->toDateString())
                        ->whereDate('starts_on', '<=', $to->toDateString());
                });
            })
            ->get();

        $leaves = 0;
        $overnights = 0;

        foreach ($filings as $filing) {
            if ($filing->type === PayrollFilingType::Overnight) {
                $overnights += (int) $filing->nights;

                continue;
            }

            $start = CarbonImmutable::parse($filing->starts_on)->timezone(ReportPeriod::ZONE)->startOfDay();
            $end = CarbonImmutable::parse($filing->ends_on ?? $filing->starts_on)->timezone(ReportPeriod::ZONE)->startOfDay();
            $overlapStart = $start->max($from);
            $overlapEnd = $end->min($to);

            if ($overlapStart->greaterThan($overlapEnd)) {
                continue;
            }

            $leaves += (int) $overlapStart->diffInDays($overlapEnd) + 1;
        }

        return [
            'leaves' => $leaves,
            'overnights' => $overnights,
        ];
    }

    /**
     * @return list<array{id: int, amount: string, reason: string|null, status: string, status_label: string, requested_on: string}>
     */
    private function cashAdvances(int $employeeId): array
    {
        return PayrollFiling::query()
            ->where('employee_id', $employeeId)
            ->where('type', PayrollFilingType::CashAdvance)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PayrollFiling $filing): array => [
                'id' => $filing->id,
                'amount' => Peso::format($filing->amount),
                'reason' => $filing->reason,
                'status' => $filing->status->value,
                'status_label' => $filing->status->label(),
                'requested_on' => $filing->starts_on?->format('M j, Y') ?? '',
            ])
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     pos_href: string|null,
     *     sales_label: string,
     *     cash_label: string,
     *     activities: list<array{key: string, title: string, detail: string, time: string, amount_label: string}>,
     *     open: list<array<string, mixed>>,
     *     online: list<array<string, mixed>>
     * }>
     */
    private function branchOrders(): array
    {
        $canOpenPos = EmployeeSession::allows('pos.access');
        $canOpenShopOrder = EmployeeSession::allows('shop.orders');
        $today = ReportPeriod::day();
        $orders = PosOpenOrder::query()
            ->where(function ($query): void {
                $query->where(function ($open): void {
                    $open->whereNull('pos_sale_id')
                        ->where(function ($status): void {
                            $status->whereNull('status')->orWhere('status', '');
                        });
                })->orWhereIn('status', [
                    ShopOrderStatus::Placed->value,
                    ShopOrderStatus::Preparing->value,
                    ShopOrderStatus::OutForDelivery->value,
                ]);
            })
            ->latest('id')
            ->get()
            ->groupBy('branch_id');
        $shifts = PosShift::query()
            ->with(['sales.payments', 'cashMovements'])
            ->where(function ($query) use ($today): void {
                $query->where(fn ($open) => $open->open())
                    ->orWhereBetween('opened_at', [$today->from, $today->to])
                    ->orWhereBetween('closed_at', [$today->from, $today->to]);
            })
            ->get()
            ->groupBy('branch_id');

        return Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'is_active'])
            ->filter(fn (Branch $branch): bool => $branch->is_active || $orders->has($branch->id))
            ->map(function (Branch $branch) use ($orders, $shifts, $today, $canOpenPos, $canOpenShopOrder): array {
                /** @var Collection<int, PosOpenOrder> $rows */
                $rows = $orders->get($branch->id, collect());
                /** @var Collection<int, PosShift> $branchShifts */
                $branchShifts = $shifts->get($branch->id, collect());
                $openShift = $branchShifts->first(fn (PosShift $shift): bool => $shift->isOpen());
                $latestClosed = $branchShifts
                    ->filter(fn (PosShift $shift): bool => $shift->closed_at !== null)
                    ->sortByDesc(fn (PosShift $shift) => $shift->closed_at?->getTimestamp() ?? 0)
                    ->first();
                $sales = $branchShifts
                    ->flatMap(fn (PosShift $shift) => $shift->sales)
                    ->filter(fn (PosSale $sale): bool => $sale->refunded_at === null
                        && $sale->sold_at !== null
                        && $sale->sold_at->between($today->from, $today->to))
                    ->sum(fn (PosSale $sale): float => (float) $sale->total);
                $cash = $openShift !== null
                    ? $this->expectedCash($openShift)
                    : (float) ($latestClosed?->counted_cash ?? 0);

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'pos_href' => $canOpenPos ? route('pos.index', $branch) : null,
                    'sales_label' => Peso::format($sales),
                    'cash_label' => Peso::format($cash),
                    'activities' => $this->shiftActivities($branchShifts),
                    'open' => $rows
                        ->filter(fn (PosOpenOrder $order): bool => $order->pos_sale_id === null && ($order->status === null || $order->status === ''))
                        ->map(fn (PosOpenOrder $order): array => $this->openOrder($order))
                        ->values()
                        ->all(),
                    'online' => $rows
                        ->filter(fn (PosOpenOrder $order): bool => ShopOrderStatus::tryFrom((string) $order->status) !== null
                            && ! in_array($order->status, [ShopOrderStatus::Completed->value, ShopOrderStatus::Cancelled->value], true))
                        ->map(fn (PosOpenOrder $order): array => $this->onlineOrder($order, $canOpenShopOrder))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function expectedCash(PosShift $shift): float
    {
        $cashSales = $shift->sales
            ->filter(fn (PosSale $sale): bool => $sale->refunded_at === null)
            ->sum(function (PosSale $sale): float {
                if ($sale->payments->isNotEmpty()) {
                    return (float) $sale->payments->where('is_cash', true)->sum(fn ($payment): float => (float) $payment->amount);
                }

                return $sale->is_cash ? (float) $sale->total : 0.0;
            });
        $cashIn = $shift->cashMovements
            ->where('type', PosCashMovementType::CashIn)
            ->sum(fn (PosCashMovement $movement): float => (float) $movement->amount);
        $cashOut = $shift->cashMovements
            ->where('type', PosCashMovementType::CashOut)
            ->sum(fn (PosCashMovement $movement): float => (float) $movement->amount);

        return (float) $shift->starting_cash + (float) $cashSales + (float) $cashIn - (float) $cashOut;
    }

    /**
     * @param  Collection<int, PosShift>  $shifts
     * @return list<array{key: string, title: string, detail: string, time: string, amount_label: string}>
     */
    private function shiftActivities(Collection $shifts): array
    {
        $rows = collect();

        foreach ($shifts as $shift) {
            $rows->push([
                'key' => 'start-'.$shift->id,
                'title' => 'Starting cash',
                'detail' => (string) $shift->opened_by_name,
                'at' => $shift->opened_at,
                'amount_label' => Peso::format($shift->starting_cash),
            ]);

            if ($shift->closed_at !== null) {
                $rows->push([
                    'key' => 'close-'.$shift->id,
                    'title' => 'Closing cash',
                    'detail' => (string) $shift->closed_by_name,
                    'at' => $shift->closed_at,
                    'amount_label' => Peso::format($shift->counted_cash),
                ]);
            }

            foreach ($shift->sales as $sale) {
                $rows->push([
                    'key' => 'sale-'.$sale->id,
                    'title' => $sale->isRefunded() ? 'Refund' : 'Transaction',
                    'detail' => collect([$sale->number, $sale->sold_by_name, $sale->payment_method_name])
                        ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                        ->implode(' · '),
                    'at' => $sale->isRefunded() ? $sale->refunded_at : $sale->sold_at,
                    'amount_label' => ($sale->isRefunded() ? '-' : '').Peso::format($sale->total),
                ]);
            }

            foreach ($shift->cashMovements as $movement) {
                if ($movement->type === PosCashMovementType::OpenDrawer) {
                    continue;
                }

                $rows->push([
                    'key' => 'cash-'.$movement->id,
                    'title' => $movement->type->label(),
                    'detail' => collect([$movement->employee_name, $movement->reason])
                        ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                        ->implode(' · '),
                    'at' => $movement->recorded_at,
                    'amount_label' => $movement->type === PosCashMovementType::CashOut
                        ? '-'.Peso::format($movement->amount)
                        : Peso::format($movement->amount),
                ]);
            }
        }

        return $rows
            ->sortByDesc(fn (array $row): int => $row['at']?->getTimestamp() ?? 0)
            ->map(function (array $row): array {
                $at = $row['at'];
                unset($row['at']);
                $row['time'] = $at === null
                    ? ''
                    : CarbonImmutable::parse($at)->timezone(ReportPeriod::ZONE)->format('M j, g:i A');

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, item_count: int, total_label: string, saved_at: string, saved_by: string, items: list<array{name: string, quantity: int}>}
     */
    private function openOrder(PosOpenOrder $order): array
    {
        return [
            'id' => $order->id,
            'name' => $order->name !== '' ? $order->name : $order->number,
            'item_count' => (int) collect($order->items ?? [])->sum('quantity'),
            'total_label' => Peso::format($order->subtotal),
            'saved_at' => $order->updated_at?->timezone(ReportPeriod::ZONE)->format('M j, g:i A') ?? '',
            'saved_by' => $order->saved_by_name,
            'items' => $this->orderItems($order),
        ];
    }

    /**
     * @return array{id: int, number: string, customer: string, status_label: string, total_label: string, placed_at: string, href: string|null, items: list<array{name: string, quantity: int}>}
     */
    private function onlineOrder(PosOpenOrder $order, bool $canOpen): array
    {
        $status = ShopOrderStatus::tryFrom((string) $order->status);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'customer' => $order->saved_by_name,
            'status_label' => $status?->label() ?? (string) $order->status,
            'total_label' => Peso::format($order->total ?? $order->subtotal),
            'placed_at' => $order->created_at?->timezone(ReportPeriod::ZONE)->format('M j, g:i A') ?? '',
            'href' => $canOpen ? route('shop.orders.show', $order) : null,
            'items' => $this->orderItems($order),
        ];
    }

    /**
     * @return list<array{name: string, quantity: int}>
     */
    private function orderItems(PosOpenOrder $order): array
    {
        return collect($order->items ?? [])
            ->map(function (array $item): array {
                $product = (string) ($item['product_name'] ?? $item['name'] ?? '');
                $variant = (string) ($item['variant_name'] ?? '');
                $name = $variant !== '' && strcasecmp($variant, 'Default') !== 0
                    ? trim($product.' · '.$variant)
                    : $product;

                return [
                    'name' => $name !== '' ? $name : 'Item',
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     previous: string,
     *     next: string,
     *     requests: list<array<string, mixed>>,
     *     events: list<array{id: int, employee: string, type: string, type_label: string, status: string, status_label: string, dates: list<string>}>,
     *     holidays: list<array{id: int, name: string, type: string, type_label: string, date: string}>
     * }
     */
    private function schedule(CommissionPeriodRequest $request): array
    {
        $month = $this->scheduleMonth($request->string('month')->toString());
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $events = PayrollFiling::query()
            ->with('employee:id,name,employee_number')
            ->whereIn('status', [PayrollFilingStatus::Pending, PayrollFilingStatus::Approved])
            ->whereIn('type', [PayrollFilingType::Leave, PayrollFilingType::Overnight])
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($leave) use ($start, $end): void {
                    $leave->where('type', PayrollFilingType::Leave)
                        ->whereDate('starts_on', '<=', $end->toDateString())
                        ->whereDate('ends_on', '>=', $start->toDateString());
                })->orWhere(function ($overnight) use ($start, $end): void {
                    $overnight->where('type', PayrollFilingType::Overnight)
                        ->whereDate('starts_on', '<=', $end->toDateString())
                        ->whereDate('starts_on', '>=', $start->subDays(30)->toDateString());
                });
            })
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->map(function (PayrollFiling $filing) use ($start, $end): ?array {
                $dates = $this->filingDates($filing, $start, $end);

                if ($dates === []) {
                    return null;
                }

                return [
                    'id' => $filing->id,
                    'employee' => (string) $filing->employee?->name,
                    'type' => $filing->type->value,
                    'type_label' => $filing->type->label(),
                    'status' => $filing->status->value,
                    'status_label' => $filing->status->label(),
                    'dates' => $dates,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'previous' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
            'requests' => PayrollFiling::query()
                ->with('employee:id,name,employee_number')
                ->where('status', PayrollFilingStatus::Pending)
                ->orderBy('starts_on')
                ->orderBy('id')
                ->get()
                ->map(fn (PayrollFiling $filing): array => $filing->toList())
                ->all(),
            'events' => $events,
            'holidays' => Holiday::query()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'type' => $holiday->type->value,
                    'type_label' => $holiday->type->label(),
                    'date' => $holiday->date->toDateString(),
                ])
                ->all(),
        ];
    }

    private function scheduleMonth(string $month): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $month, ReportPeriod::ZONE);
        } catch (\Throwable) {
            $parsed = false;
        }

        if ($parsed instanceof CarbonImmutable && $parsed->format('Y-m') === $month) {
            return $parsed->startOfMonth();
        }

        return CarbonImmutable::now(ReportPeriod::ZONE)->startOfMonth();
    }

    /**
     * @return list<string>
     */
    private function filingDates(PayrollFiling $filing, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $start = CarbonImmutable::parse($filing->starts_on)->startOfDay();
        $end = $filing->type === PayrollFilingType::Overnight
            ? $start->addDays(max((int) $filing->nights, 1) - 1)
            : CarbonImmutable::parse($filing->ends_on ?? $filing->starts_on)->startOfDay();
        $cursor = $start->max($monthStart->startOfDay());
        $last = $end->min($monthEnd->startOfDay());
        $dates = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array{scope: string, filters?: array{preset: string, from: string, to: string, label: string}, report: array{label: string, total: string, employees: list<array{name: string, employee_number: string, amount: string}>}}|null
     */
    private function commissions(
        CommissionPeriodRequest $request,
        ShopReports $reports,
        bool $canSeeAllCommissions,
        ?int $employeeId,
    ): ?array {
        if ($canSeeAllCommissions) {
            return [
                'scope' => 'all',
                'report' => $reports->commissions(ReportPeriod::monthToDate()),
            ];
        }

        if ($employeeId === null) {
            return null;
        }

        $period = $request->period();

        return [
            'scope' => 'self',
            'filters' => $period->toArray(),
            'report' => $reports->commissions($period, $employeeId),
        ];
    }
}
