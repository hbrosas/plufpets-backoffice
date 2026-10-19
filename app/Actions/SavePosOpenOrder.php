<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\PosOpenOrder;
use App\Models\PosShift;
use Illuminate\Validation\ValidationException;

class SavePosOpenOrder
{
    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  array{name: string, items: list<array<string, mixed>>}  $attributes
     */
    public function handle(Branch $branch, array $attributes, array $employee, ?PosOpenOrder $openOrder = null): PosOpenOrder
    {
        $this->ensureOpenShift($branch);

        $items = $attributes['items'];
        $subtotal = collect($items)->sum(
            fn (array $item): float => (float) $item['price'] * (int) $item['quantity'],
        );

        $payload = [
            'name' => $attributes['name'],
            'items' => $items,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'saved_by_employee_number' => $employee['employee_number'],
            'saved_by_name' => $employee['name'],
        ];

        if ($openOrder !== null) {
            $openOrder->update($payload);

            return $openOrder->refresh();
        }

        return PosOpenOrder::query()->create([
            ...$payload,
            'branch_id' => $branch->id,
            'number' => $this->nextNumber($branch->id),
        ]);
    }

    private function ensureOpenShift(Branch $branch): void
    {
        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        if ($shift === null) {
            throw ValidationException::withMessages([
                'status' => 'Open a shift before parking an order.',
            ]);
        }
    }

    private function nextNumber(int $branchId): string
    {
        $prefix = now()->format('Ymd');
        $count = PosOpenOrder::query()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return sprintf('OPEN-%s-%04d', $prefix, $count + 1);
    }
}
