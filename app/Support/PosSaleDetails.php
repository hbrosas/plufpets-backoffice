<?php

namespace App\Support;

use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSaleItemCommission;
use App\Models\PosSalePayment;
use App\Models\PosSaleTax;

class PosSaleDetails
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(PosSale $sale, ?string $timezone = null): array
    {
        $timezone ??= (string) config('app.timezone');
        $sale->loadMissing(['branch:id,name', 'items.commissions', 'payments', 'taxes']);

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at?->timezone($timezone)->format('M j, Y g:i A'),
            'branch' => $sale->branch?->name,
            'cashier' => $sale->sold_by_name,
            'employee_number' => $sale->sold_by_employee_number,
            'delivery' => $sale->delivery_method_name,
            'subtotal' => Peso::format($sale->subtotal),
            'delivery_fee' => Peso::format($sale->delivery_fee),
            'tax' => Peso::format($sale->tax_total),
            'total' => Peso::format($sale->total),
            'is_refunded' => $sale->isRefunded(),
            'refunded_at' => $sale->refunded_at?->timezone($timezone)->format('M j, Y g:i A'),
            'refunded_by' => $sale->refunded_by_name,
            'refund_reason' => $sale->refund_reason,
            'items' => $sale->items->map(fn (PosSaleItem $item): array => [
                'id' => $item->id,
                'product' => $item->product_name,
                'variant' => $item->is_free ? trim($item->variant_name.' · Free') : $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => Peso::format($item->unit_price),
                'line_total' => Peso::format($item->line_total),
                'notes' => $item->notes,
                'modifiers' => $item->modifiers ?? [],
            ]),
            'payments' => $sale->payments->map(fn (PosSalePayment $payment): array => [
                'name' => $payment->payment_method_name,
                'amount' => Peso::format($payment->amount),
                'tendered' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null,
                'change' => $payment->is_cash ? Peso::format($payment->change_due) : null,
                'reference' => $payment->reference_number,
            ]),
            'taxes' => $sale->taxes->map(fn (PosSaleTax $tax): array => [
                'name' => $tax->name,
                'amount' => Peso::format($tax->amount),
            ]),
            'commissions' => self::commissions($sale, $timezone),
        ];
    }

    /**
     * @return list<array{id: int, employee_id: int|null, employee_number: string, employee_name: string, role: string, item: string, amount: string, waived: bool, waived_at: string|null, waived_by: string|null}>
     */
    private static function commissions(PosSale $sale, string $timezone): array
    {
        return $sale->items
            ->flatMap(fn (PosSaleItem $item) => $item->commissions->map(fn (PosSaleItemCommission $commission): array => [
                'id' => $commission->id,
                'employee_id' => $commission->employee_id,
                'employee_number' => $commission->employee_number,
                'employee_name' => $commission->employee_name,
                'role' => ucfirst($commission->role->value),
                'item' => self::itemLabel($item),
                'amount' => Peso::format($commission->amount),
                'waived' => $commission->waived_at !== null,
                'waived_at' => $commission->waived_at?->timezone($timezone)->format('M j, Y g:i A'),
                'waived_by' => $commission->waived_by_name,
            ]))
            ->sortBy('id')
            ->values()
            ->all();
    }

    private static function itemLabel(PosSaleItem $item): string
    {
        $variant = trim((string) $item->variant_name);

        if ($variant !== '' && strcasecmp($variant, 'Default') !== 0) {
            return $item->product_name.' · '.$variant;
        }

        return (string) $item->product_name;
    }
}
