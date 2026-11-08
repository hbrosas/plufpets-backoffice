<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryScheduleFrequency;
use App\Enums\DeliveryScheduleKind;
use App\Http\Requests\StoreDeliveryScheduleRequest;
use App\Http\Requests\UpdateDeliveryScheduleRequest;
use App\Models\DeliverySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
}
