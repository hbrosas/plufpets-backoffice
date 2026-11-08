<?php

namespace App\Http\Controllers;

use App\Actions\SendShopOrderText;
use App\Actions\ShopOrderStock;
use App\Enums\ShopOrderStatus;
use App\Http\Requests\AssignShopOrderDeliveryScheduleRequest;
use App\Http\Requests\UpdateShopOrderRequest;
use App\Models\Branch;
use App\Models\CatalogItemVariant;
use App\Models\DeliverySchedule;
use App\Models\Employee;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Support\EmployeeSession;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopOrderController extends Controller
{
    public function index(): Response
    {
        $orders = PosOpenOrder::query()
            ->with([
                'branch:id,name',
                'deliverySchedule',
                'sale.branch:id,name',
                'sale.items.commissions',
                'sale.payments',
                'sale.taxes',
            ])
            ->whereNotNull('status')
            ->latest('id')
            ->limit(100)
            ->get();
        $employees = $this->employeesForOrders($orders);

        return Inertia::render('Shop/Orders/Index', [
            'orders' => $orders
                ->map(fn (PosOpenOrder $order): array => $this->detail($order, $employees))
                ->all(),
            'preorders' => $this->preorders(),
            'statuses' => ShopOrderStatus::options(),
            'schedules' => $this->schedules(),
            'branches' => Branch::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->all(),
        ]);
    }

    public function exportPreorders(): StreamedResponse
    {
        $rows = $this->preorders();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Order', 'Branch', 'Brand', 'Category', 'Name', 'Variant', 'Quantity', 'Price']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['order_number'],
                    $row['branch'],
                    $row['brand'],
                    $row['category'],
                    $row['name'],
                    $row['variant'],
                    $row['quantity'],
                    $row['price_label'],
                ]);
            }

            fclose($handle);
        }, 'preorders.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(PosOpenOrder $posOpenOrder): Response
    {
        $this->ensureShopOrder($posOpenOrder);

        return Inertia::render('Shop/Orders/Show', [
            'order' => $this->detail($this->loadOrderDetail($posOpenOrder)),
            'statuses' => ShopOrderStatus::options(),
            'schedules' => $this->schedules(),
        ]);
    }

    public function updateDeliverySchedule(AssignShopOrderDeliveryScheduleRequest $request, PosOpenOrder $posOpenOrder): RedirectResponse
    {
        $this->ensureShopOrder($posOpenOrder);
        $scheduleId = $request->validated('delivery_schedule_id');
        $posOpenOrder->update([
            'delivery_schedule_id' => $scheduleId,
        ]);

        return back()->with('status', $scheduleId === null ? 'Delivery schedule removed.' : 'Delivery schedule assigned.');
    }

    public function update(UpdateShopOrderRequest $request, PosOpenOrder $posOpenOrder, SendShopOrderText $sendShopOrderText, ShopOrderStock $shopOrderStock): RedirectResponse
    {
        $this->ensureShopOrder($posOpenOrder);

        $status = $request->string('status')->toString();
        $message = 'Order status saved.';

        if ($posOpenOrder->status !== $status) {
            DB::transaction(function () use ($posOpenOrder, $status, $shopOrderStock): void {
                if ($status === ShopOrderStatus::Cancelled->value) {
                    $shopOrderStock->release($posOpenOrder);
                }

                $posOpenOrder->update(['status' => $status]);
            });
            $smsError = $sendShopOrderText->handle($posOpenOrder->fresh(['branch']) ?? $posOpenOrder);

            if (is_string($smsError)) {
                $message = 'Order status saved. '.$smsError;
            }
        }

        return back()->with('status', $message);
    }

    private function ensureShopOrder(PosOpenOrder $order): void
    {
        abort_unless(is_string($order->status) && $order->status !== '', 404);
    }

    private function loadOrderDetail(PosOpenOrder $order): PosOpenOrder
    {
        $order->load([
            'branch:id,name',
            'deliverySchedule',
            'sale.branch:id,name',
            'sale.items.commissions',
            'sale.payments',
            'sale.taxes',
        ]);

        return $order;
    }

    /**
     * @return list<array{id: int, branch_id: int|null, branch: string|null, label: string, is_active: bool, has_ended: bool, is_assignable: bool, orders: list<array{id: int, number: string, customer: string|null, branch: string|null, status: string, status_label: string}>}>
     */
    private function schedules(): array
    {
        return DeliverySchedule::query()
            ->with([
                'branch:id,name',
                'orders' => function ($query): void {
                    $query->select(['id', 'delivery_schedule_id', 'branch_id', 'number', 'saved_by_name', 'status'])
                        ->with('branch:id,name')
                        ->whereNotNull('status')
                        ->where('status', '!=', '')
                        ->latest('id');
                },
            ])
            ->orderByDesc('scheduled_on')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DeliverySchedule $schedule): array => [
                'id' => $schedule->id,
                'branch_id' => $schedule->branch_id,
                'branch' => $schedule->branch?->name,
                'label' => $schedule->label(),
                'is_active' => $schedule->is_active,
                'has_ended' => $schedule->hasEnded(),
                'is_assignable' => $schedule->isAssignable(),
                'orders' => $schedule->orders->map(function (PosOpenOrder $order): array {
                    $status = ShopOrderStatus::tryFrom((string) $order->status);

                    return [
                        'id' => $order->id,
                        'number' => $order->number,
                        'customer' => $order->saved_by_name,
                        'branch' => $order->branch?->name,
                        'status' => $status?->value ?? (string) $order->status,
                        'status_label' => $status?->label() ?? (string) $order->status,
                    ];
                })->all(),
            ])
            ->all();
    }

    /**
     * @return list<array{order_number: string, branch: string, brand: string, category: string, name: string, variant: string, quantity: int, price_label: string}>
     */
    private function preorders(): array
    {
        $orders = PosOpenOrder::query()
            ->with('branch:id,name')
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->whereNotIn('status', [ShopOrderStatus::Completed->value, ShopOrderStatus::Cancelled->value])
            ->orderByDesc('id')
            ->get();

        $variantIds = [];

        foreach ($orders as $order) {
            foreach ($order->items ?? [] as $item) {
                if (! is_array($item) || ($item['notes'] ?? null) !== 'Preorder') {
                    continue;
                }

                if (isset($item['catalog_item_variant_id'])) {
                    $variantIds[] = (int) $item['catalog_item_variant_id'];
                }
            }
        }

        $variants = CatalogItemVariant::query()
            ->with(['catalogItem.brand', 'catalogItem.category'])
            ->whereIn('id', array_values(array_unique($variantIds)))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($orders as $order) {
            foreach ($order->items ?? [] as $item) {
                if (! is_array($item) || ($item['notes'] ?? null) !== 'Preorder') {
                    continue;
                }

                $variant = $variants->get((int) ($item['catalog_item_variant_id'] ?? 0));
                $catalogItem = $variant?->catalogItem;
                $variantName = trim((string) ($item['variant_name'] ?? ''));

                $lines[] = [
                    'order_number' => (string) $order->number,
                    'branch' => (string) ($order->branch?->name ?? ''),
                    'brand' => (string) ($catalogItem?->brand?->name ?? ''),
                    'category' => (string) ($catalogItem?->category?->name ?? ''),
                    'name' => (string) ($item['product_name'] ?? $catalogItem?->name ?? 'Item'),
                    'variant' => $variantName !== '' ? $variantName : (string) ($variant?->name ?? ''),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'price_label' => Peso::format($item['price'] ?? 0),
                ];
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PosOpenOrder $order): array
    {
        $checkout = is_array($order->checkout) ? $order->checkout : [];
        $address = is_array($checkout['address'] ?? null) ? $checkout['address'] : null;
        $status = ShopOrderStatus::tryFrom((string) $order->status);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'customer' => $order->saved_by_name,
            'phone' => $order->phone,
            'branch_id' => $order->branch_id,
            'branch' => $order->branch?->name,
            'status' => $status?->value ?? $order->status,
            'status_label' => $status?->label() ?? $order->status,
            'subtotal_label' => Peso::format($order->subtotal),
            'discount_label' => Peso::format($order->discount),
            'total_label' => Peso::format($order->total ?? $order->subtotal),
            'delivery' => $checkout['delivery']['name'] ?? null,
            'delivery_fee_label' => isset($checkout['delivery']['fee']) ? Peso::format($checkout['delivery']['fee']) : null,
            'payment' => $checkout['payment']['name'] ?? null,
            'payment_fee_label' => isset($checkout['payment']['fee']) ? Peso::format($checkout['payment']['fee']) : null,
            'voucher' => $checkout['voucher']['code'] ?? null,
            'address' => $this->addressLabel($address),
            'coordinates' => $this->coordinates($address),
            'items' => collect($order->items)->map(fn (mixed $item): string => $this->itemLabel($item))->filter()->values()->all(),
            'sale_number' => $order->sale?->number,
            'placed_at' => $order->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'delivery_schedule_id' => $order->delivery_schedule_id,
            'preferred_on' => $order->preferred_on?->format('M j, Y'),
            'schedule' => $order->deliverySchedule?->label(),
        ];
    }

    /**
     * @param  Collection<int, Employee>|null  $employees
     * @return array<string, mixed>
     */
    private function detail(PosOpenOrder $order, ?Collection $employees = null): array
    {
        $checkout = is_array($order->checkout) ? $order->checkout : [];
        $delivery = is_array($checkout['delivery'] ?? null) ? $checkout['delivery'] : [];
        $payment = is_array($checkout['payment'] ?? null) ? $checkout['payment'] : [];
        $address = is_array($checkout['address'] ?? null) ? $checkout['address'] : null;
        $status = ShopOrderStatus::tryFrom((string) $order->status);
        $employees ??= $this->employeesFor($order);

        return [
            ...$this->payload($order),
            'delivery_instructions' => $delivery['instructions'] ?? null,
            'delivery_instruction_images' => $this->imageUrls($delivery['instruction_images'] ?? null),
            'payment_instructions' => $payment['instructions'] ?? null,
            'payment_instruction_images' => $this->imageUrls($payment['instruction_images'] ?? null),
            'voucher_discount_label' => isset($checkout['voucher']['discount']) ? Peso::format($checkout['voucher']['discount']) : null,
            'lines' => collect($order->items)->map(function (mixed $item) use ($employees): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);

                return [
                    'name' => (string) ($item['product_name'] ?? 'Item'),
                    'variant' => $this->variantName($item),
                    'quantity' => $quantity,
                    'price_label' => Peso::format($price),
                    'line_total_label' => Peso::format($price * $quantity),
                    'notes' => is_string($item['notes'] ?? null) && $item['notes'] !== '' ? $item['notes'] : null,
                    'modifiers' => $this->modifierLabels($item['modifiers'] ?? []),
                    'assigned' => $this->employeeNames($item['assigned_employee_ids'] ?? [], $employees),
                    'assisted' => $this->employeeNames($item['assisted_employee_ids'] ?? [], $employees),
                ];
            })->filter()->values()->all(),
            'sale' => $order->sale instanceof PosSale ? $this->salePayload($order->sale) : null,
            'can_view_receipt' => $order->sale instanceof PosSale && EmployeeSession::allows('sales.view'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(PosSale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'branch' => $sale->branch?->name,
            'cashier' => $sale->sold_by_name,
            'employee_number' => $sale->sold_by_employee_number,
            'delivery' => $sale->delivery_method_name,
            'subtotal_label' => Peso::format($sale->subtotal),
            'delivery_fee_label' => Peso::format($sale->delivery_fee),
            'tax_label' => Peso::format($sale->tax_total),
            'total_label' => Peso::format($sale->total),
            'tendered_label' => Peso::format($sale->amount_tendered),
            'change_label' => Peso::format($sale->change_due),
            'is_refunded' => $sale->isRefunded(),
            'refunded_at' => $sale->refunded_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'refunded_by' => $sale->refunded_by_name,
            'refund_reason' => $sale->refund_reason,
            'items' => $sale->items->map(fn (PosSaleItem $item): array => [
                'id' => $item->id,
                'product' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price_label' => Peso::format($item->unit_price),
                'line_total_label' => Peso::format($item->line_total),
                'notes' => $item->notes !== '' ? $item->notes : null,
                'modifiers' => $this->modifierLabels($item->modifiers ?? []),
                'commissions' => $item->commissions->map(fn ($commission): array => [
                    'employee' => $commission->employee_name,
                    'role' => ucfirst($commission->role->value),
                    'amount_label' => Peso::format($commission->amount),
                ])->all(),
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment): array => [
                'name' => $payment->payment_method_name,
                'amount_label' => Peso::format($payment->amount),
                'tendered_label' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null,
                'change_label' => $payment->is_cash ? Peso::format($payment->change_due) : null,
                'reference' => $payment->reference_number !== '' ? $payment->reference_number : null,
            ])->all(),
            'taxes' => $sale->taxes->map(fn ($tax): array => [
                'name' => $tax->name,
                'amount_label' => Peso::format($tax->amount),
            ])->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function imageUrls(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter($images, fn (mixed $url): bool => is_string($url) && $url !== ''));
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function addressLabel(?array $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $label = collect([
            $address['line_1'] ?? null,
            $address['line_2'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
        ])->filter(fn (mixed $part): bool => is_string($part) && $part !== '')->implode(', ');

        return $label !== '' ? $label : null;
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function coordinates(?array $address): ?string
    {
        if (! isset($address['latitude'], $address['longitude']) || ! $address['latitude'] || ! $address['longitude']) {
            return null;
        }

        return $address['latitude'].', '.$address['longitude'];
    }

    private function itemLabel(mixed $item): string
    {
        if (! is_array($item)) {
            return '';
        }

        $name = (string) ($item['product_name'] ?? 'Item');
        $variant = $this->variantName($item);

        if ($variant !== null) {
            $name .= ' · '.$variant;
        }

        return $name.' × '.(int) ($item['quantity'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function variantName(array $item): ?string
    {
        $variant = trim((string) ($item['variant_name'] ?? ''));

        if ($variant === '' || strcasecmp($variant, 'Default') === 0) {
            return null;
        }

        return $variant;
    }

    /**
     * @return list<string>
     */
    private function modifierLabels(mixed $modifiers): array
    {
        if (! is_array($modifiers)) {
            return [];
        }

        return collect($modifiers)->map(function (mixed $modifier): string {
            if (! is_array($modifier)) {
                return '';
            }

            $name = trim((string) ($modifier['name'] ?? ''));
            $option = trim((string) ($modifier['option'] ?? ''));

            if ($name !== '' && $option !== '') {
                return $name.': '.$option;
            }

            return $option !== '' ? $option : $name;
        })->filter()->values()->all();
    }

    /**
     * @param  Collection<int, PosOpenOrder>  $orders
     * @return Collection<int, Employee>
     */
    private function employeesForOrders(Collection $orders): Collection
    {
        $ids = $orders
            ->flatMap(fn (PosOpenOrder $order): array => $this->employeeIds($order))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Employee::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesFor(PosOpenOrder $order): Collection
    {
        $ids = $this->employeeIds($order);

        if ($ids === []) {
            return collect();
        }

        return Employee::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @return list<int>
     */
    private function employeeIds(PosOpenOrder $order): array
    {
        return collect($order->items)
            ->flatMap(function (mixed $item): array {
                if (! is_array($item)) {
                    return [];
                }

                return [
                    ...$this->idList($item['assigned_employee_ids'] ?? []),
                    ...$this->idList($item['assisted_employee_ids'] ?? []),
                ];
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<string>
     */
    private function employeeNames(mixed $ids, Collection $employees): array
    {
        return collect($this->idList($ids))
            ->map(fn (int $id): string => $employees->get($id)?->name ?? '')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}
