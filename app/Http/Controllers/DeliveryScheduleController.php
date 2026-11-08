<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use App\Enums\ShopOrderStatus;
use App\Http\Requests\StoreDeliveryScheduleRequest;
use App\Http\Requests\UpdateDeliveryScheduleRequest;
use App\Models\DeliverySchedule;
use App\Models\PosOpenOrder;
use App\Support\Peso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DeliveryScheduleController extends Controller
{
    public function store(StoreDeliveryScheduleRequest $request): RedirectResponse
    {
        $kind = DeliveryScheduleKind::from($request->string('kind')->toString());
        $frequency = $kind === DeliveryScheduleKind::Frequency
            ? DeliveryScheduleFrequency::from($request->string('frequency')->toString())
            : null;
        $weekdays = $frequency === DeliveryScheduleFrequency::Weekly
            ? collect($request->validated('weekdays'))->map(fn (mixed $day): int => (int) $day)->sort()->values()->all()
            : null;
        $date = $kind === DeliveryScheduleKind::Date ? $request->string('scheduled_on')->toString() : null;

        DB::transaction(function () use ($request, $kind, $frequency, $weekdays, $date): void {
            foreach ($request->validated('slots') as $slot) {
                DeliverySchedule::query()->create([
                    'branch_id' => $request->integer('branch_id'),
                    'kind' => $kind,
                    'frequency' => $frequency,
                    'weekdays' => $weekdays,
                    'scheduled_on' => $date,
                    'starts_at' => $slot['time'].':00',
                    'is_active' => true,
                ]);
            }
        });

        return back()->with('status', 'Delivery schedule created.');
    }

    public function update(UpdateDeliveryScheduleRequest $request, DeliverySchedule $deliverySchedule): RedirectResponse
    {
        $isActive = $request->boolean('is_active');
        $deliverySchedule->update([
            'is_active' => $isActive,
        ]);

        return back()->with('status', $isActive ? 'Delivery schedule reopened.' : 'Delivery schedule closed.');
    }

    public function export(DeliverySchedule $deliverySchedule): Response
    {
        $sheet = $this->sheet($deliverySchedule);

        return Pdf::loadView('shop.delivery-schedules.export', $sheet)
            ->setPaper('a4', 'landscape')
            ->download($sheet['filename']);
    }

    /**
     * @return array{filename: string, label: string, branch: string|null, orders: list<array{number: string, customer: string, phone: string, preferred_on: string, address: string, items: string, status: string, total: string}>}
     */
    protected function sheet(DeliverySchedule $deliverySchedule): array
    {
        $deliverySchedule->loadMissing('branch:id,name');

        $orders = $deliverySchedule->orders()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->orderBy('preferred_on')
            ->orderBy('id')
            ->get()
            ->map(fn (PosOpenOrder $order): array => [
                'number' => $order->number,
                'customer' => (string) $order->saved_by_name,
                'phone' => (string) ($order->phone ?? ''),
                'preferred_on' => $order->preferred_on?->format('M j, Y') ?? '',
                'address' => $this->addressLabel($order),
                'items' => $this->itemLabels($order),
                'status' => ShopOrderStatus::tryFrom((string) $order->status)?->label() ?? (string) $order->status,
                'total' => Peso::format($order->total ?? $order->subtotal),
            ])
            ->all();

        $label = $deliverySchedule->label();
        $slug = Str::slug($label);

        return [
            'filename' => ($slug !== '' ? $slug : 'delivery-schedule').'.pdf',
            'label' => $label,
            'branch' => $deliverySchedule->branch?->name,
            'orders' => $orders,
        ];
    }

    private function addressLabel(PosOpenOrder $order): string
    {
        $checkout = is_array($order->checkout) ? $order->checkout : [];
        $address = is_array($checkout['address'] ?? null) ? $checkout['address'] : [];

        return collect([
            $address['line_1'] ?? null,
            $address['line_2'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
        ])->filter(fn (mixed $part): bool => is_string($part) && $part !== '')->implode(', ');
    }

    private function itemLabels(PosOpenOrder $order): string
    {
        return collect($order->items ?? [])
            ->map(function (mixed $item): string {
                if (! is_array($item)) {
                    return '';
                }

                $name = trim((string) ($item['product_name'] ?? 'Item'));
                $variant = trim((string) ($item['variant_name'] ?? ''));
                $quantity = (int) ($item['quantity'] ?? 0);
                $label = $variant !== '' ? $name.' '.$variant : $name;

                return $quantity > 0 ? $label.' × '.$quantity : $label;
            })
            ->filter(fn (string $label): bool => $label !== '')
            ->implode('; ');
    }
}
