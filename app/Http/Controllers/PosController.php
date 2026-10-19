<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\PosCashMovementType;
use App\Models\Branch;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\DeliveryMethod;
use App\Models\Modifier;
use App\Models\PaymentMethod;
use App\Models\PosCashMovement;
use App\Models\PosCategory;
use App\Models\PosOpenOrder;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Printer;
use App\Models\TaxFee;
use App\Support\CashDenominations;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    /**
     * @param  array<string, mixed>  $notice
     */
    public function index(Request $request, Branch $branch, array $notice = []): Response
    {
        abort_unless($branch->is_active, 404);

        $partial = array_values(array_filter(explode(',', (string) $request->header('X-Inertia-Partial-Data'))));

        if ($partial === [] && ! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            $partial = ['shift', 'todayTransactions', 'todayTransactionsTotal', 'openOrders', 'flash'];
        }

        $wants = fn (string $key): bool => $partial === [] || in_array($key, $partial, true);

        $today = null;
        $todayTransactions = function () use ($branch, &$today): array {
            return $today ??= $this->todayTransactions($branch);
        };

        $props = [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'denominations' => CashDenominations::values(),
        ];

        if ($wants('shift')) {
            $props['shift'] = $this->currentShiftPayload($branch);
        }

        if ($wants('categories')) {
            $props['categories'] = PosCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name']);
        }

        if ($wants('variants')) {
            $props['variants'] = $this->variantsForBranch($branch->id);
        }

        if ($wants('paymentMethods')) {
            $props['paymentMethods'] = PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'instructions' => $method->instructions ?? '',
                    'is_cash' => $method->is_cash,
                ]);
        }

        if ($wants('deliveryMethods')) {
            $props['deliveryMethods'] = DeliveryMethod::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (DeliveryMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'fee' => $method->fee,
                    'fee_label' => $method->fee === null ? 'Set in cart' : Peso::format($method->fee),
                ]);
        }

        if ($wants('taxFees')) {
            $props['taxFees'] = TaxFee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (TaxFee $taxFee): array => [
                    'id' => $taxFee->id,
                    'name' => $taxFee->name,
                    'type' => $taxFee->type->value,
                    'rate' => $taxFee->rate,
                ]);
        }

        if ($wants('todayTransactions') || $wants('todayTransactionsTotal')) {
            $todayPayload = $todayTransactions();

            if ($wants('todayTransactions')) {
                $props['todayTransactions'] = $todayPayload['items'];
            }

            if ($wants('todayTransactionsTotal')) {
                $props['todayTransactionsTotal'] = $todayPayload['sales_total_label'];
            }
        }

        if ($wants('printers')) {
            $props['printers'] = Printer::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('role')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Printer $printer): array => $printer->toSettingsPayload())
                ->values();
        }

        if ($wants('openOrders')) {
            $props['openOrders'] = PosOpenOrder::query()
                ->where('branch_id', $branch->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PosOpenOrder $order): array => $this->openOrderPayload($order));
        }

        if ($wants('flash') || $notice !== []) {
            $props['flash'] = $this->flashPayload($request, $notice);
        }

        return Inertia::render('Pos/Index', $props);
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    public function fromMutation(Request $request, Branch $branch, array $flash = []): RedirectResponse|Response
    {
        if ($request->header('X-Inertia')) {
            return $this->index($request, $branch, $flash);
        }

        foreach ($flash as $key => $value) {
            $request->session()->flash($key, $value);
        }

        return redirect()->route('pos.index', $branch);
    }

    /**
     * @param  array<string, mixed>  $notice
     * @return array<string, mixed>
     */
    private function flashPayload(Request $request, array $notice): array
    {
        $status = $notice['status'] ?? $request->session()->get('status');

        return [
            'status' => $status,
            'status_at' => $status ? microtime(true) : null,
            'import_errors' => $request->session()->get('import_errors'),
            'created_category' => $request->session()->get('created_category'),
            'created_brand' => $request->session()->get('created_brand'),
            'new_variant' => $request->session()->get('new_variant'),
            'open_drawer' => $notice['open_drawer'] ?? $request->session()->get('open_drawer'),
            'checkout_success' => $notice['checkout_success'] ?? $request->session()->get('checkout_success'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentShiftPayload(Branch $branch): ?array
    {
        $shift = PosShift::query()
            ->with(['sales.items', 'cashMovements'])
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        return $shift === null ? null : $this->shiftPayload($shift);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variantsForBranch(int $branchId): array
    {
        return CatalogItemVariant::query()
            ->where('is_active', true)
            ->whereHas('catalogItem', fn ($query) => $query->where('is_active', true))
            ->with([
                'catalogItem.category',
                'catalogItem.modifiers',
                'catalogItem.modifierOptionPrices.branchPrices' => fn ($query) => $query->where('branch_id', $branchId),
                'inventories' => fn ($query) => $query->where('branch_id', $branchId),
                'branchPrices' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (CatalogItemVariant $variant) use ($branchId): array {
                $price = (float) $variant->price;

                if ($variant->catalogItem?->has_branch_pricing) {
                    $branchPrice = $variant->branchPrices->firstWhere('branch_id', $branchId);
                    if ($branchPrice !== null) {
                        $price = (float) $branchPrice->price;
                    }
                }

                $isProduct = $variant->catalogItem?->type === CatalogItemType::Product;
                $quantity = $isProduct ? (int) ($variant->inventories->first()?->quantity ?? 0) : null;
                $shopCategory = $variant->catalogItem?->category;

                return [
                    'id' => $variant->id,
                    'product_name' => $variant->catalogItem?->name ?? $variant->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'image_url' => $variant->imageUrl(),
                    'pos_category_id' => $shopCategory?->pos_category_id,
                    'shop_category_name' => $shopCategory?->name ?? 'Uncategorized',
                    'price' => number_format($price, 2, '.', ''),
                    'price_label' => Peso::format($price),
                    'quantity' => $quantity,
                    'is_product' => $isProduct,
                    'modifiers' => $this->modifiersForItem($variant->catalogItem, $branchId),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, options: list<array{name: string, price: string, price_label: string}>}>
     */
    private function modifiersForItem(?CatalogItem $item, int $branchId): array
    {
        if ($item === null) {
            return [];
        }

        return $item->modifiers
            ->filter(fn (Modifier $modifier): bool => $modifier->is_active && ($modifier->options ?? []) !== [])
            ->values()
            ->map(function (Modifier $modifier) use ($item, $branchId): array {
                return [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'options' => collect($modifier->options ?? [])
                        ->map(function (mixed $option) use ($item, $modifier, $branchId): array {
                            $name = (string) $option;
                            $price = $this->modifierOptionPrice($item, $modifier->id, $name, $branchId);

                            return [
                                'name' => $name,
                                'price' => number_format($price, 2, '.', ''),
                                'price_label' => Peso::format($price),
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();
    }

    private function modifierOptionPrice(CatalogItem $item, int $modifierId, string $option, int $branchId): float
    {
        $row = $item->modifierOptionPrices
            ->first(fn (CatalogItemModifierOptionPrice $price): bool => $price->modifier_id === $modifierId && $price->option === $option);

        if ($row === null) {
            return 0.0;
        }

        if ($item->has_branch_pricing) {
            $branchPrice = $row->branchPrices->firstWhere('branch_id', $branchId);

            if ($branchPrice !== null) {
                return (float) $branchPrice->price;
            }
        }

        return (float) $row->price;
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftPayload(PosShift $shift): array
    {
        return [
            'id' => $shift->id,
            'opened_at' => $shift->opened_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'opened_by_name' => $shift->opened_by_name,
            'starting_cash' => $shift->starting_cash,
            'starting_cash_label' => Peso::format($shift->starting_cash),
            'cash_sales_total' => $shift->cashSalesTotal(),
            'cash_sales_total_label' => Peso::format($shift->cashSalesTotal()),
            'cash_in_total' => $shift->cashInTotal(),
            'cash_in_total_label' => Peso::format($shift->cashInTotal()),
            'cash_out_total' => $shift->cashOutTotal(),
            'cash_out_total_label' => Peso::format($shift->cashOutTotal()),
            'expected_cash' => $shift->expectedDrawerCash(),
            'expected_cash_label' => Peso::format($shift->expectedDrawerCash()),
            'sales_count' => $shift->sales()->count(),
            'sales_total_label' => Peso::format($shift->sales()->sum('total')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openOrderPayload(PosOpenOrder $order): array
    {
        $items = $order->items ?? [];

        return [
            'id' => $order->id,
            'number' => $order->number,
            'name' => $order->name !== '' ? $order->name : $order->number,
            'item_count' => collect($items)->sum('quantity'),
            'subtotal' => $order->subtotal,
            'subtotal_label' => Peso::format($order->subtotal),
            'saved_at' => $order->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            'saved_by_name' => $order->saved_by_name,
            'items' => $items,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, sales_total_label: string}
     */
    private function todayTransactions(Branch $branch): array
    {
        $sales = PosSale::query()
            ->with(['items', 'payments'])
            ->where('branch_id', $branch->id)
            ->whereDate('sold_at', now()->toDateString())
            ->get();

        $movements = PosCashMovement::query()
            ->whereHas('shift', fn ($query) => $query->where('branch_id', $branch->id))
            ->whereDate('recorded_at', now()->toDateString())
            ->get();

        $startingShifts = PosShift::query()
            ->where('branch_id', $branch->id)
            ->where(function ($query): void {
                $query->whereDate('opened_at', now()->toDateString())
                    ->orWhere(fn ($open) => $open->open());
            })
            ->get();

        $items = $startingShifts->map(fn (PosShift $shift): array => [
            'key' => 'start-'.$shift->id,
            'type' => 'starting_cash',
            'title' => 'Starting cash',
            'sort' => $shift->opened_at?->timestamp ?? 0,
            'time' => $this->clockLabel($shift->opened_at),
            'actor_name' => $shift->opened_by_name,
            'detail' => null,
            'amount_label' => Peso::format($shift->starting_cash),
            'payments' => [],
            'items' => [],
        ])->concat($movements->map(function (PosCashMovement $movement): array {
            $isCashOut = $movement->type === PosCashMovementType::CashOut;
            $amountLabel = Peso::format($movement->amount);

            return [
                'key' => 'cash-'.$movement->id,
                'type' => $movement->type->value,
                'title' => $movement->type->label(),
                'sort' => $movement->recorded_at?->timestamp ?? 0,
                'time' => $this->clockLabel($movement->recorded_at),
                'actor_name' => $movement->employee_name,
                'detail' => $movement->reason,
                'amount_label' => $isCashOut ? '-'.$amountLabel : $amountLabel,
                'payments' => [],
                'items' => [],
            ];
        }))->concat($sales->map(function (PosSale $sale): array {
            $payload = $this->salePayload($sale);

            return [
                'key' => 'sale-'.$sale->id,
                'type' => 'sale',
                'title' => $sale->number,
                'sort' => $sale->sold_at?->timestamp ?? 0,
                'time' => $payload['sold_at'],
                'actor_name' => $sale->sold_by_name,
                'detail' => $sale->payment_method_name,
                'amount_label' => $payload['total_label'],
                'payments' => $payload['payments'],
                'items' => $payload['items'],
            ];
        }))
            ->sortByDesc('sort')
            ->values()
            ->map(function (array $row): array {
                unset($row['sort']);

                return $row;
            })
            ->all();

        return [
            'items' => $items,
            'sales_total_label' => Peso::format($sales->sum('total')),
        ];
    }

    private function clockLabel(?Carbon $time): string
    {
        return $time?->timezone(config('app.timezone'))->format('g:i A') ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(PosSale $sale): array
    {
        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at?->timezone(config('app.timezone'))->format('g:i A'),
            'sold_by_name' => $sale->sold_by_name,
            'payment_method_name' => $sale->payment_method_name,
            'payments' => $sale->payments->map(fn ($payment): array => [
                'name' => $payment->payment_method_name,
                'amount_label' => Peso::format($payment->amount),
                'is_cash' => $payment->is_cash,
                'reference_number' => $payment->reference_number,
                'change_due_label' => Peso::format($payment->change_due),
            ]),
            'total_label' => Peso::format($sale->total),
            'items' => $sale->items->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->variant_name === 'Default'
                    ? $item->product_name
                    : $item->product_name.' · '.$item->variant_name,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
                'modifiers' => $item->modifiers ?? [],
                'line_total_label' => Peso::format($item->line_total),
            ]),
        ];
    }
}
