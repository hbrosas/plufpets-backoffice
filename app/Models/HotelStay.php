<?php

namespace App\Models;

use App\Support\Peso;
use App\Support\ReportPeriod;
use Database\Factories\HotelStayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'pos_shift_id',
    'client_name',
    'contact_number',
    'address',
    'checked_in_at',
    'checkout_on',
    'checked_out_at',
    'hotel_pod_type_id',
    'checked_in_by_employee_number',
    'checked_in_by_name',
    'checked_out_by_employee_number',
    'checked_out_by_name',
])]
class HotelStay extends Model
{
    /** @use HasFactory<HotelStayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checkout_on' => 'date',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function checkedIn(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    /**
     * @return BelongsTo<HotelPodType, $this>
     */
    public function podType(): BelongsTo
    {
        return $this->belongsTo(HotelPodType::class, 'hotel_pod_type_id');
    }

    /**
     * @return HasMany<HotelStayPet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(HotelStayPet::class)->orderBy('id');
    }

    /**
     * @return HasMany<HotelStayPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(HotelStayPayment::class)->orderByDesc('recorded_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<PosSale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class)->orderByDesc('sold_at')->orderByDesc('id');
    }

    /**
     * @return HasOne<PosOpenOrder, $this>
     */
    public function guestCart(): HasOne
    {
        return $this->hasOne(PosOpenOrder::class);
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_out_at === null;
    }

    public function availablePayment(): float
    {
        $paid = (float) $this->payments->sum(fn (HotelStayPayment $payment): float => (float) $payment->amount);
        $used = (float) $this->sales
            ->reject(fn (PosSale $sale): bool => $sale->isRefunded())
            ->sum(fn (PosSale $sale): float => (float) $sale->hotel_credit);

        return max(0, round($paid - $used, 2));
    }

    /**
     * @return array<string, mixed>
     */
    public function toRegisterPayload(): array
    {
        $this->loadMissing(['pets', 'payments', 'sales', 'podType', 'branch', 'guestCart']);

        $checkedIn = $this->checked_in_at?->timezone(ReportPeriod::ZONE);
        $available = $this->availablePayment();

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name ?? '',
            'client_name' => $this->client_name,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
            'checked_in_label' => $checkedIn?->format('M j, Y g:i A') ?? '',
            'checkout_on' => $this->checkout_on?->toDateString() ?? '',
            'checkout_label' => $this->checkout_on?->format('M j, Y') ?? '',
            'pod_type_label' => $this->podType?->name ?? '',
            'pet_names' => $this->pets->pluck('name')->join(', '),
            'pets' => $this->pets->map(fn (HotelStayPet $pet): array => $pet->toRegisterPayload())->all(),
            'payments_total_label' => Peso::format($this->payments->sum(fn (HotelStayPayment $payment): float => (float) $payment->amount)),
            'payments_available' => number_format($available, 2, '.', ''),
            'payments_available_label' => Peso::format($available),
            'payments' => $this->payments->map(fn (HotelStayPayment $payment): array => [
                'id' => $payment->id,
                'method' => $payment->payment_method_name,
                'amount_label' => Peso::format($payment->amount),
                'tendered_label' => $payment->is_cash ? Peso::format($payment->amount_tendered) : null,
                'change_label' => $payment->is_cash && (float) $payment->change_due > 0 ? Peso::format($payment->change_due) : null,
                'reference' => $payment->reference_number,
                'recorded_at_label' => $payment->recorded_at?->timezone(ReportPeriod::ZONE)->format('M j, Y g:i A') ?? '',
            ])->all(),
            'sales' => $this->sales->map(fn (PosSale $sale): array => [
                'id' => $sale->id,
                'number' => $sale->number,
                'total_label' => Peso::format($sale->total),
                'sold_at_label' => $sale->sold_at?->timezone(ReportPeriod::ZONE)->format('M j, Y g:i A') ?? '',
                'refunded' => $sale->isRefunded(),
            ])->all(),
            'cart' => $this->guestCartPayload(),
        ];
    }

    /**
     * @return array{open_order_id: int|null, subtotal_label: string, items: list<array<string, mixed>>}
     */
    private function guestCartPayload(): array
    {
        $order = $this->guestCart;
        $rows = is_array($order?->items) ? $order->items : [];
        $subtotal = 0.0;
        $items = [];

        foreach (array_values($rows) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $free = (bool) ($item['is_free'] ?? false);
            $line = round($price * $quantity, 2);
            $subtotal += $free ? -$line : $line;
            $variant = (string) ($item['variant_name'] ?? '');
            $name = (string) ($item['product_name'] ?? 'Item');
            $label = $variant !== '' && strcasecmp($variant, 'Default') !== 0
                ? $name.' · '.$variant
                : $name;

            $items[] = [
                'key' => $index,
                'label' => $free ? $label.' · Free' : $label,
                'quantity' => $quantity,
                'line_total_label' => Peso::format($free ? -$line : $line),
                'catalog_item_variant_id' => (int) ($item['catalog_item_variant_id'] ?? 0),
                'product_name' => $name,
                'variant_name' => $variant,
                'price' => number_format($price, 2, '.', ''),
                'notes' => is_string($item['notes'] ?? null) ? $item['notes'] : '',
                'modifiers' => is_array($item['modifiers'] ?? null) ? $item['modifiers'] : [],
                'assigned_employee_ids' => is_array($item['assigned_employee_ids'] ?? null) ? $item['assigned_employee_ids'] : [],
                'assisted_employee_ids' => is_array($item['assisted_employee_ids'] ?? null) ? $item['assisted_employee_ids'] : [],
                'is_product' => (bool) ($item['is_product'] ?? true),
                'is_free' => $free,
            ];
        }

        return [
            'open_order_id' => $order?->id,
            'subtotal_label' => Peso::format($subtotal),
            'items' => $items,
        ];
    }
}
