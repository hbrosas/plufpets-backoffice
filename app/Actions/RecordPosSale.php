<?php

namespace App\Actions;

use App\Enums\CatalogItemType;
use App\Enums\TaxFeeType;
use App\Models\BranchInventory;
use App\Models\CatalogItem;
use App\Models\CatalogItemModifierOptionPrice;
use App\Models\CatalogItemVariant;
use App\Models\DeliveryMethod;
use App\Models\Modifier;
use App\Models\PaymentMethod;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\PosSaleTax;
use App\Models\PosShift;
use App\Models\TaxFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPosSale
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  array{
     *     items: list<array{catalog_item_variant_id: int, quantity: int, notes?: string|null, modifiers?: list<array{modifier_id: int, option: string}>}>,
     *     payments: list<array{payment_method_id: int, amount: mixed, amount_tendered?: mixed, reference_number?: string|null}>,
     *     delivery_method_id?: int|null,
     *     delivery_fee?: mixed
     * }  $attributes
     */
    public function handle(PosShift $shift, array $attributes, array $employee): PosSale
    {
        return DB::transaction(function () use ($shift, $attributes, $employee): PosSale {
            $shift = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Open a shift before recording a sale.',
                ]);
            }

            $deliveryMethod = null;
            $deliveryFee = 0.0;

            if (! empty($attributes['delivery_method_id'])) {
                $deliveryMethod = DeliveryMethod::query()->findOrFail($attributes['delivery_method_id']);

                if (! $deliveryMethod->is_active) {
                    throw ValidationException::withMessages([
                        'delivery_method_id' => 'Choose an active delivery method.',
                    ]);
                }

                $deliveryFee = array_key_exists('delivery_fee', $attributes) && $attributes['delivery_fee'] !== null
                    ? (float) $attributes['delivery_fee']
                    : (float) ($deliveryMethod->fee ?? 0);
            }

            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($attributes['items'] as $index => $item) {
                $variant = CatalogItemVariant::query()
                    ->with([
                        'catalogItem.modifiers',
                        'catalogItem.modifierOptionPrices.branchPrices' => fn ($query) => $query->where('branch_id', $shift->branch_id),
                        'branchPrices' => fn ($query) => $query->where('branch_id', $shift->branch_id),
                    ])
                    ->whereKey($item['catalog_item_variant_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $variant->is_active || ! $variant->catalogItem?->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.catalog_item_variant_id" => 'That item is not available.',
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $isProduct = $variant->catalogItem?->type === CatalogItemType::Product;

                if ($isProduct) {
                    $inventory = BranchInventory::query()
                        ->where('branch_id', $shift->branch_id)
                        ->where('catalog_item_variant_id', $variant->id)
                        ->lockForUpdate()
                        ->first();

                    $onHand = $inventory?->quantity ?? 0;

                    if ($quantity > $onHand) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => 'Only '.$onHand.' units are available at this branch.',
                        ]);
                    }
                }

                $selectedModifiers = $this->selectedModifiers($variant, $item['modifiers'] ?? [], $index, $shift->branch_id);
                $unitPrice = $this->unitPrice($variant, $shift->branch_id) + $selectedModifiers['extra'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $preparedItems[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'notes' => isset($item['notes']) && is_string($item['notes']) ? trim($item['notes']) : null,
                    'modifiers' => $selectedModifiers['snapshot'],
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                    'decrement_inventory' => $isProduct,
                ];
            }

            $taxable = round($subtotal + $deliveryFee, 2);
            $taxRows = [];
            $taxTotal = 0.0;

            foreach (TaxFee::query()->where('is_active', true)->orderBy('name')->orderBy('id')->get() as $taxFee) {
                $amount = $taxFee->type === TaxFeeType::Percent
                    ? round($taxable * ((float) $taxFee->rate) / 100, 2)
                    : (float) $taxFee->rate;

                $taxTotal += $amount;
                $taxRows[] = [
                    'tax_fee' => $taxFee,
                    'amount' => number_format($amount, 2, '.', ''),
                ];
            }

            $total = round($taxable + $taxTotal, 2);
            $preparedPayments = $this->preparedPayments($attributes['payments'] ?? [], $total);
            $primaryPayment = $preparedPayments[0];
            $hasCash = collect($preparedPayments)->contains(fn (array $payment): bool => $payment['is_cash']);
            $tendered = collect($preparedPayments)->sum(fn (array $payment): float => (float) $payment['amount_tendered']);
            $changeDue = collect($preparedPayments)->sum(fn (array $payment): float => (float) $payment['change_due']);

            $sale = PosSale::query()->create([
                'pos_shift_id' => $shift->id,
                'branch_id' => $shift->branch_id,
                'number' => $this->nextNumber($shift->branch_id),
                'payment_method_id' => $primaryPayment['payment_method_id'],
                'payment_method_name' => collect($preparedPayments)->pluck('payment_method_name')->unique()->implode(' + '),
                'is_cash' => $hasCash,
                'delivery_method_id' => $deliveryMethod?->id,
                'delivery_method_name' => $deliveryMethod?->name,
                'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'tax_total' => number_format($taxTotal, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
                'amount_tendered' => number_format((float) $tendered, 2, '.', ''),
                'change_due' => number_format($changeDue, 2, '.', ''),
                'sold_by_employee_number' => $employee['employee_number'],
                'sold_by_name' => $employee['name'],
                'sold_at' => now(),
            ]);

            foreach ($preparedItems as $prepared) {
                /** @var CatalogItemVariant $variant */
                $variant = $prepared['variant'];

                PosSaleItem::query()->create([
                    'pos_sale_id' => $sale->id,
                    'catalog_item_variant_id' => $variant->id,
                    'product_name' => $variant->catalogItem?->name ?? $variant->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'notes' => $prepared['notes'] !== '' ? $prepared['notes'] : null,
                    'modifiers' => $prepared['modifiers'] === [] ? null : $prepared['modifiers'],
                    'quantity' => $prepared['quantity'],
                    'unit_price' => $prepared['unit_price'],
                    'line_total' => $prepared['line_total'],
                ]);

                if ($prepared['decrement_inventory']) {
                    BranchInventory::query()
                        ->where('branch_id', $shift->branch_id)
                        ->where('catalog_item_variant_id', $variant->id)
                        ->decrement('quantity', $prepared['quantity']);
                }
            }

            foreach ($preparedPayments as $preparedPayment) {
                PosSalePayment::query()->create([
                    'pos_sale_id' => $sale->id,
                    'payment_method_id' => $preparedPayment['payment_method_id'],
                    'payment_method_name' => $preparedPayment['payment_method_name'],
                    'is_cash' => $preparedPayment['is_cash'],
                    'amount' => $preparedPayment['amount'],
                    'amount_tendered' => $preparedPayment['amount_tendered'],
                    'change_due' => $preparedPayment['change_due'],
                    'reference_number' => $preparedPayment['reference_number'],
                ]);
            }

            foreach ($taxRows as $taxRow) {
                PosSaleTax::query()->create([
                    'pos_sale_id' => $sale->id,
                    'tax_fee_id' => $taxRow['tax_fee']->id,
                    'name' => $taxRow['tax_fee']->name,
                    'type' => $taxRow['tax_fee']->type,
                    'rate' => $taxRow['tax_fee']->rate,
                    'amount' => $taxRow['amount'],
                ]);
            }

            return $sale->load(['items', 'taxes', 'payments']);
        });
    }

    /**
     * @param  list<array{payment_method_id?: mixed, amount?: mixed, amount_tendered?: mixed, reference_number?: mixed}>  $payments
     * @return list<array{payment_method_id: int, payment_method_name: string, is_cash: bool, amount: string, amount_tendered: string, change_due: string, reference_number: string|null}>
     */
    private function preparedPayments(array $payments, float $total): array
    {
        $prepared = [];
        $applied = 0.0;

        foreach ($payments as $index => $payment) {
            $method = PaymentMethod::query()->findOrFail($payment['payment_method_id']);

            if (! $method->is_active) {
                throw ValidationException::withMessages([
                    "payments.{$index}.payment_method_id" => 'Choose an active payment method.',
                ]);
            }

            $amount = round((float) $payment['amount'], 2);

            if ($amount < 0.01) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Enter a payment amount.',
                ]);
            }

            $tendered = $payment['amount_tendered'] ?? null;
            $reference = isset($payment['reference_number']) ? trim((string) $payment['reference_number']) : '';
            $changeDue = 0.0;

            if ($method->is_cash) {
                if ($tendered === null || $tendered === '') {
                    throw ValidationException::withMessages([
                        "payments.{$index}.amount_tendered" => 'Enter the amount tendered.',
                    ]);
                }

                if ((float) $tendered + 0.005 < $amount) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.amount_tendered" => 'Amount tendered is less than the payment.',
                    ]);
                }

                $changeDue = round((float) $tendered - $amount, 2);
            } else {
                if ($reference === '') {
                    throw ValidationException::withMessages([
                        "payments.{$index}.reference_number" => 'Enter the reference number.',
                    ]);
                }

                $tendered = $amount;
            }

            $applied += $amount;
            $prepared[] = [
                'payment_method_id' => $method->id,
                'payment_method_name' => $method->name,
                'is_cash' => $method->is_cash,
                'amount' => number_format($amount, 2, '.', ''),
                'amount_tendered' => number_format((float) $tendered, 2, '.', ''),
                'change_due' => number_format($changeDue, 2, '.', ''),
                'reference_number' => $method->is_cash ? null : $reference,
            ];
        }

        if (abs($applied - $total) > 0.005) {
            throw ValidationException::withMessages([
                'payments' => 'Payments must equal the sale total.',
            ]);
        }

        return $prepared;
    }

    /**
     * @param  list<array{modifier_id?: mixed, option?: mixed}>  $selections
     * @return array{extra: float, snapshot: list<array{name: string, option: string, price: string}>}
     */
    private function selectedModifiers(CatalogItemVariant $variant, array $selections, int $index, int $branchId): array
    {
        $catalogItem = $variant->catalogItem;
        $extra = 0.0;
        $snapshot = [];

        foreach ($selections as $selection) {
            $option = trim((string) ($selection['option'] ?? ''));

            if ($option === '') {
                continue;
            }

            if ($catalogItem === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.modifiers" => 'That item does not have modifiers.',
                ]);
            }

            $modifierId = (int) ($selection['modifier_id'] ?? 0);
            $modifier = $catalogItem->modifiers->firstWhere('id', $modifierId);

            if (! $modifier instanceof Modifier || ! $modifier->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.modifiers" => 'Choose a modifier that is enabled on this item.',
                ]);
            }

            if (! in_array($option, $modifier->options ?? [], true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.modifiers" => 'Choose a valid option for '.$modifier->name.'.',
                ]);
            }

            $price = $this->modifierOptionPrice($catalogItem, $modifierId, $option, $branchId);
            $extra += $price;
            $snapshot[] = [
                'name' => $modifier->name,
                'option' => $option,
                'price' => number_format($price, 2, '.', ''),
            ];
        }

        return [
            'extra' => $extra,
            'snapshot' => $snapshot,
        ];
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

    private function unitPrice(CatalogItemVariant $variant, int $branchId): float
    {
        if ($variant->catalogItem?->has_branch_pricing) {
            $branchPrice = $variant->branchPrices->firstWhere('branch_id', $branchId);

            if ($branchPrice !== null) {
                return (float) $branchPrice->price;
            }
        }

        return (float) $variant->price;
    }

    private function nextNumber(int $branchId): string
    {
        $prefix = now()->format('Ymd');
        $latestNumber = PosSale::query()
            ->where('branch_id', $branchId)
            ->whereDate('sold_at', now()->toDateString())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('number');

        $sequence = 1;

        if (is_string($latestNumber) && preg_match('/-(\d+)$/', $latestNumber, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('POS-%s-%04d', $prefix, $sequence);
    }
}
