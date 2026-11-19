<?php

namespace App\Actions;

use App\Enums\CommissionRole;
use App\Models\EmployeeServiceCommission;
use App\Models\PosSale;
use App\Models\PosSaleItemCommission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecomputePosSaleCommission
{
    public function __construct(private CalculateServiceCommission $calculateServiceCommission) {}

    public function handle(PosSale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $sale = PosSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->refunded_at !== null) {
                throw ValidationException::withMessages([
                    'commission' => 'This receipt is refunded.',
                ]);
            }

            $commissions = PosSaleItemCommission::query()
                ->with('saleItem.variant:id,catalog_item_id')
                ->whereHas('saleItem', fn ($query) => $query->where('pos_sale_id', $sale->id))
                ->get();

            if ($commissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'commission' => 'This receipt has no commissions.',
                ]);
            }

            $employeeIds = $commissions->pluck('employee_id')->filter()->unique()->values();
            $catalogItemIds = $commissions
                ->map(fn (PosSaleItemCommission $commission): ?int => $commission->saleItem?->variant?->catalog_item_id)
                ->filter()
                ->unique()
                ->values();

            $rates = EmployeeServiceCommission::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereIn('catalog_item_id', $catalogItemIds)
                ->get()
                ->keyBy(fn (EmployeeServiceCommission $rate): string => $rate->employee_id.'-'.$rate->catalog_item_id);

            foreach ($commissions->groupBy(fn (PosSaleItemCommission $commission): string => $commission->pos_sale_item_id.'|'.$commission->role->value) as $pool) {
                $poolSize = $pool->count();

                foreach ($pool as $commission) {
                    $rate = $this->rate($commission, $rates);

                    $commission->update([
                        'rate' => $rate,
                        'pool_size' => $poolSize,
                        'amount' => $this->calculateServiceCommission->share(
                            (float) $commission->commissionable_amount,
                            (float) $rate,
                            $poolSize,
                        ),
                    ]);
                }
            }
        });
    }

    /**
     * @param  Collection<string, EmployeeServiceCommission>  $rates
     */
    private function rate(PosSaleItemCommission $commission, Collection $rates): string
    {
        $catalogItemId = $commission->saleItem?->variant?->catalog_item_id;

        if ($commission->employee_id === null || $catalogItemId === null) {
            return number_format((float) $commission->rate, 2, '.', '');
        }

        $saved = $rates->get($commission->employee_id.'-'.$catalogItemId);
        $percent = $commission->role === CommissionRole::Assisted
            ? (float) ($saved?->assisted_rate ?? 0)
            : (float) ($saved?->assigned_rate ?? 0);

        return number_format($percent, 2, '.', '');
    }
}
