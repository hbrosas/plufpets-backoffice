<?php

namespace App\Http\Controllers;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Enums\PosCashMovementType;
use App\Enums\PosDenominationCountType;
use App\Enums\ShopOrderStatus;
use App\Http\Requests\CommissionPeriodRequest;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\HotelStay;
use App\Models\HotelStayPet;
use App\Models\PayrollFiling;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Support\CashDenominations;
use App\Support\DashboardPulse;
use App\Support\EmployeeSession;
use App\Support\Peso;
use App\Support\PosSaleDetails;
use App\Support\ReportPeriod;
use App\Support\ShopReports;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

        $trend = $request->string('trend')->toString();

        return Inertia::render('Dashboard', [
            'pulse' => $canSeeSales ? $pulse->today() : null,
            'categoryTrend' => $canSeeSales ? $pulse->categoryTrend($trend) : null,
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
     *     start_cash: array{title: string, time: string, amount_label: string, variance_label: null, receipt: null, fields: list<array{label: string, value: string}>, counts: list<array{denomination: string, quantity: int, total: string}>},
     *     close_cash: array{title: string, time: string, amount_label: string, variance_label: string|null, receipt: null, fields: list<array{label: string, value: string}>, counts: list<array{denomination: string, quantity: int, total: string}>},
     *     activities: list<array{key: string, title: string, detail: string, time: string, amount_label: string, receipt: array<string, mixed>|null, fields: list<array{label: string, value: string}>}>,
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
            ->with([
                'sales.branch:id,name',
                'sales.items.commissions',
                'sales.payments',
                'sales.taxes',
                'cashMovements',
                'denominations',
                'hotelPayments.stay:id,client_name',
            ])
            ->where(function ($query) use ($today): void {
                $query->where(fn ($open) => $open->open())
                    ->orWhereBetween('opened_at', [$today->from, $today->to])
                    ->orWhereBetween('closed_at', [$today->from, $today->to]);
            })
            ->get()
            ->groupBy('branch_id');
        $stays = HotelStay::query()
            ->with(['pets', 'podType:id,name'])
            ->where(function ($query) use ($shifts, $today): void {
                $query->whereIn('pos_shift_id', $shifts->flatten()->pluck('id'))
                    ->orWhereBetween('checked_out_at', [$today->from, $today->to]);
            })
            ->get()
            ->groupBy('branch_id');

        return Branch::query()
            ->operational()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'is_active'])
            ->filter(fn (Branch $branch): bool => $branch->is_active || $orders->has($branch->id))
            ->map(function (Branch $branch) use ($orders, $shifts, $stays, $today, $canOpenPos, $canOpenShopOrder): array {
                /** @var Collection<int, PosOpenOrder> $rows */
                $rows = $orders->get($branch->id, collect());
                /** @var Collection<int, PosShift> $branchShifts */
                $branchShifts = $shifts->get($branch->id, collect());
                /** @var Collection<int, HotelStay> $branchStays */
                $branchStays = $stays->get($branch->id, collect());
                $openShift = $branchShifts->first(fn (PosShift $shift): bool => $shift->isOpen());
                $latestClosed = $branchShifts
                    ->filter(fn (PosShift $shift): bool => $shift->closed_at !== null)
                    ->sortByDesc(fn (PosShift $shift) => $shift->closed_at?->getTimestamp() ?? 0)
                    ->first();
                $todaySales = $branchShifts
                    ->flatMap(fn (PosShift $shift) => $shift->sales)
                    ->filter(fn (PosSale $sale): bool => $sale->refunded_at === null
                        && $sale->sold_at !== null
                        && $sale->sold_at->between($today->from, $today->to))
                    ->values();
                $sales = $todaySales->sum(fn (PosSale $sale): float => (float) $sale->total);
                $cashShift = $openShift ?? $latestClosed;

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'pos_href' => $canOpenPos ? route('pos.index', $branch) : null,
                    'sales_label' => Peso::format($sales),
                    'sales_payments' => $this->salesPayments($todaySales),
                    'start_cash' => $this->cashSummary($cashShift, PosDenominationCountType::Opening),
                    'close_cash' => $this->cashSummary($cashShift, PosDenominationCountType::Closing),
                    'activities' => $this->shiftActivities($branchShifts, $branchStays, $today),
                    'open' => $rows
                        ->filter(fn (PosOpenOrder $order): bool => $order->pos_sale_id === null
                            && $order->hotel_stay_id === null
                            && ($order->status === null || $order->status === ''))
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

    /**
     * @param  Collection<int, PosSale>  $sales
     * @return list<array{name: string, amount_label: string}>
     */
    private function salesPayments(Collection $sales): array
    {
        return $sales
            ->flatMap(function (PosSale $sale): array {
                if ($sale->payments->isNotEmpty()) {
                    return $sale->payments
                        ->map(fn ($payment): array => [
                            'name' => trim((string) $payment->payment_method_name),
                            'amount' => (float) $payment->amount,
                        ])
                        ->filter(fn (array $row): bool => $row['name'] !== '')
                        ->all();
                }

                $name = trim((string) $sale->payment_method_name);

                if ($name === '') {
                    return [];
                }

                return [[
                    'name' => $name,
                    'amount' => (float) $sale->total,
                ]];
            })
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'amount' => (float) $rows->sum('amount'),
            ])
            ->sortBy([
                ['amount', 'desc'],
                ['name', 'asc'],
            ])
            ->map(fn (array $row): array => [
                'name' => $row['name'],
                'amount_label' => Peso::format($row['amount']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, time: string, amount_label: string, variance_label: string|null, receipt: null, fields: list<array{label: string, value: string}>, counts: list<array{denomination: string, quantity: int, total: string}>}
     */
    private function cashSummary(?PosShift $shift, PosDenominationCountType $type): array
    {
        $closing = $type === PosDenominationCountType::Closing;
        $counted = $shift !== null && $shift->closed_at !== null;
        $amount = match (true) {
            $shift === null => null,
            $closing && ! $counted => null,
            $closing => $shift->counted_cash,
            default => $shift->starting_cash,
        };
        $at = $closing ? $shift?->closed_at : $shift?->opened_at;

        return [
            'title' => $closing ? 'Close Cash' : 'Start Cash',
            'time' => $at === null
                ? ''
                : CarbonImmutable::parse($at)->timezone(ReportPeriod::ZONE)->format('M j, g:i A'),
            'amount_label' => $amount === null ? '—' : Peso::format($amount),
            'variance_label' => $closing && $counted ? $this->varianceLabel($shift->variance) : null,
            'receipt' => null,
            'fields' => $this->activityFields($closing ? [
                ['label' => 'Closed by', 'value' => $counted ? $this->personLabel($shift->closed_by_name, $shift->closed_by_employee_number) : null],
            ] : [
                ['label' => 'Opened by', 'value' => $shift === null ? null : $this->personLabel($shift->opened_by_name, $shift->opened_by_employee_number)],
            ]),
            'counts' => $shift === null || ($closing && ! $counted) ? [] : $this->cashCount($shift, $type),
        ];
    }

    /**
     * @param  Collection<int, PosShift>  $shifts
     * @param  Collection<int, HotelStay>  $stays
     * @return list<array{key: string, title: string, detail: string, time: string, amount_label: string, receipt: array<string, mixed>|null, fields: list<array{label: string, value: string}>, counts: list<array{denomination: string, quantity: int, total: string}>|null}>
     */
    private function shiftActivities(Collection $shifts, Collection $stays, ReportPeriod $today): array
    {
        $rows = collect();
        $hotelCashMovementIds = $shifts
            ->flatMap(fn (PosShift $shift) => $shift->hotelPayments)
            ->pluck('pos_cash_movement_id')
            ->filter()
            ->all();

        foreach ($shifts as $shift) {
            $rows->push([
                'key' => 'start-'.$shift->id,
                'title' => 'Starting cash',
                'detail' => (string) $shift->opened_by_name,
                'at' => $shift->opened_at,
                'amount_label' => Peso::format($shift->starting_cash),
                'receipt' => null,
                'fields' => $this->activityFields([
                    ['label' => 'Opened by', 'value' => $this->personLabel($shift->opened_by_name, $shift->opened_by_employee_number)],
                ]),
                'counts' => $this->cashCount($shift, PosDenominationCountType::Opening),
            ]);

            if ($shift->closed_at !== null) {
                $rows->push([
                    'key' => 'close-'.$shift->id,
                    'title' => 'Closing cash',
                    'detail' => (string) $shift->closed_by_name,
                    'at' => $shift->closed_at,
                    'amount_label' => Peso::format($shift->counted_cash),
                    'receipt' => null,
                    'fields' => $this->activityFields([
                        ['label' => 'Closed by', 'value' => $this->personLabel($shift->closed_by_name, $shift->closed_by_employee_number)],
                        ['label' => 'Expected cash', 'value' => $shift->expected_cash === null ? null : Peso::format($shift->expected_cash)],
                        ['label' => 'Variance', 'value' => $this->varianceLabel($shift->variance)],
                        ['label' => 'Remit', 'value' => $shift->remit_amount === null ? null : Peso::format($shift->remit_amount)],
                    ]),
                    'counts' => $this->cashCount($shift, PosDenominationCountType::Closing),
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
                    'receipt' => PosSaleDetails::payload($sale, ReportPeriod::ZONE),
                    'fields' => [],
                    'counts' => null,
                ]);
            }

            foreach ($shift->hotelPayments as $payment) {
                $rows->push([
                    'key' => 'hotel-payment-'.$payment->id,
                    'title' => 'Hotel payment',
                    'detail' => (string) $payment->stay?->client_name,
                    'at' => $payment->recorded_at,
                    'amount_label' => Peso::format($payment->amount),
                    'receipt' => null,
                    'fields' => $this->activityFields([
                        ['label' => 'Client', 'value' => $payment->stay?->client_name],
                        ['label' => 'Payment', 'value' => $payment->payment_method_name],
                        ['label' => 'Tendered', 'value' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null],
                        ['label' => 'Change', 'value' => $payment->is_cash ? Peso::format($payment->change_due) : null],
                        ['label' => 'Reference', 'value' => $payment->reference_number],
                        ['label' => 'Recorded by', 'value' => $this->personLabel($payment->recorded_by_name, $payment->recorded_by_employee_number)],
                    ]),
                    'counts' => null,
                ]);
            }

            foreach ($shift->cashMovements as $movement) {
                if ($movement->type === PosCashMovementType::OpenDrawer || in_array($movement->id, $hotelCashMovementIds, true)) {
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
                    'receipt' => null,
                    'fields' => $this->activityFields([
                        ['label' => 'Recorded by', 'value' => $this->personLabel($movement->employee_name, $movement->employee_number)],
                        ['label' => 'Reason', 'value' => $movement->reason],
                    ]),
                    'counts' => null,
                ]);
            }
        }

        $loadedShiftIds = $shifts->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach ($stays as $stay) {
            $onLoadedShift = in_array((int) $stay->pos_shift_id, $loadedShiftIds, true);

            if ($onLoadedShift) {
                $rows->push($this->hotelStayActivity($stay, false));
            }

            $checkedOutToday = $stay->checked_out_at !== null
                && $stay->checked_out_at->between($today->from, $today->to);

            if ($stay->checked_out_at !== null && ($onLoadedShift || $checkedOutToday)) {
                $rows->push($this->hotelStayActivity($stay, true));
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
     * @return array{key: string, title: string, detail: string, at: CarbonInterface|null, amount_label: string, receipt: null, fields: list<array{label: string, value: string}>, counts: null}
     */
    private function hotelStayActivity(HotelStay $stay, bool $checkout): array
    {
        $pets = $stay->pets
            ->map(fn (HotelStayPet $pet): string => collect([$pet->name, $pet->breed, $pet->gender->label()])
                ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                ->implode(' · '))
            ->implode(', ');

        return [
            'key' => ($checkout ? 'hotel-checkout-' : 'hotel-checkin-').$stay->id,
            'title' => $checkout ? 'Hotel check-out' : 'Hotel check-in',
            'detail' => collect([$stay->client_name, $stay->pets->pluck('name')->filter()->join(', '), $stay->podType?->name, $this->stayDateLabel($stay)])
                ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                ->implode(' · '),
            'at' => $checkout ? $stay->checked_out_at : $stay->checked_in_at,
            'amount_label' => '',
            'receipt' => null,
            'fields' => $this->activityFields([
                ['label' => 'Client', 'value' => $stay->client_name],
                ['label' => 'Contact', 'value' => $stay->contact_number],
                ['label' => 'Address', 'value' => $checkout ? null : $stay->address],
                ['label' => 'Pod', 'value' => $stay->podType?->name],
                ['label' => 'Pets', 'value' => $pets !== '' ? $pets : null],
                ['label' => 'Stay dates', 'value' => $this->stayDateLabel($stay)],
                ['label' => $checkout ? 'Checked out by' : 'Checked in by', 'value' => $this->personLabel(
                    $checkout ? $stay->checked_out_by_name : $stay->checked_in_by_name,
                    $checkout ? $stay->checked_out_by_employee_number : $stay->checked_in_by_employee_number,
                )],
            ]),
            'counts' => null,
        ];
    }

    private function stayDateLabel(HotelStay $stay): ?string
    {
        $checkedIn = $stay->checked_in_at?->timezone(ReportPeriod::ZONE)->format('M j, Y');
        $checkout = $stay->checkout_on?->format('M j, Y');
        $dates = array_values(array_filter(
            [$checkedIn, $checkout],
            fn (?string $date): bool => $date !== null && $date !== '',
        ));

        return match (count($dates)) {
            0 => null,
            1 => $dates[0],
            default => $dates[0].' – '.$dates[1],
        };
    }

    /**
     * @param  list<array{label: string, value: string|null}>  $fields
     * @return list<array{label: string, value: string}>
     */
    private function activityFields(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            fn (array $field): bool => $field['value'] !== null && $field['value'] !== '',
        ));
    }

    /**
     * @return list<array{denomination: string, quantity: int, total: string}>
     */
    private function cashCount(PosShift $shift, PosDenominationCountType $type): array
    {
        return CashDenominations::breakdown(
            $shift->denominations->where('count_type', $type),
        );
    }

    private function personLabel(?string $name, ?string $number): string
    {
        $name = trim((string) $name);
        $number = trim((string) $number);

        if ($name === '') {
            return $number === '' ? '' : '#'.$number;
        }

        if ($number === '') {
            return $name;
        }

        return $name.' · #'.$number;
    }

    private function varianceLabel(mixed $variance): ?string
    {
        if ($variance === null || $variance === '') {
            return null;
        }

        $amount = (float) $variance;

        if ($amount === 0.0) {
            return Peso::format(0);
        }

        return ($amount < 0 ? 'Short ' : 'Over ').Peso::format(abs($amount));
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
     *     holidays: list<array{id: int, name: string, type: string, type_label: string, date: string}>,
     *     guests: list<array{id: int, client: string, pets: string, pod_type: string, branch: string, paid_label: string, dates: list<string>}>
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

        $guests = HotelStay::query()
            ->with(['pets:id,hotel_stay_id,name', 'podType:id,name', 'branch:id,name'])
            ->withSum('payments', 'amount')
            ->where('checked_in_at', '<=', $end->utc())
            ->where(function ($query) use ($start): void {
                $query->whereNull('checked_out_at')
                    ->orWhere('checked_out_at', '>=', $start->utc())
                    ->orWhereDate('checkout_on', '>=', $start->toDateString());
            })
            ->orderBy('checked_in_at')
            ->orderBy('id')
            ->get()
            ->map(function (HotelStay $stay) use ($start, $end): ?array {
                $dates = $this->stayDates($stay, $start, $end);

                if ($dates === []) {
                    return null;
                }

                return [
                    'id' => $stay->id,
                    'client' => $stay->client_name,
                    'pets' => $stay->pets->pluck('name')->join(', '),
                    'pod_type' => $stay->podType?->name ?? '',
                    'branch' => (string) $stay->branch?->name,
                    'paid_label' => Peso::format($stay->payments_sum_amount ?? 0),
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
            'guests' => $guests,
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
     * Days this guest occupies during the visible month.
     * A guest who is still checked in continues through today when the planned checkout has passed.
     * A guest who has checked out stays on the calendar through the later of the planned checkout and the day they left.
     *
     * @return list<string>
     */
    private function stayDates(HotelStay $stay, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $start = CarbonImmutable::parse($stay->checked_in_at)->timezone(ReportPeriod::ZONE)->startOfDay();
        $checkoutOn = CarbonImmutable::parse($stay->checkout_on->toDateString(), ReportPeriod::ZONE)->startOfDay();
        $today = CarbonImmutable::now(ReportPeriod::ZONE)->startOfDay();
        $checkedOutOn = $stay->checked_out_at === null
            ? null
            : CarbonImmutable::parse($stay->checked_out_at)->timezone(ReportPeriod::ZONE)->startOfDay();

        if ($checkedOutOn === null) {
            $end = $today->greaterThan($checkoutOn) ? $today : $checkoutOn;
        } else {
            $end = $checkedOutOn->greaterThan($checkoutOn) ? $checkedOutOn : $checkoutOn;
        }
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
