<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\PosShift;

class PosLaunchPads
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function branches(): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($branches->isEmpty()) {
            return [];
        }

        $branchIds = $branches->modelKeys();

        $openShifts = PosShift::query()
            ->open()
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy('branch_id');

        $closedShifts = PosShift::query()
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->get()
            ->unique('branch_id')
            ->keyBy('branch_id');

        $timezone = config('app.timezone');

        return $branches
            ->map(function (Branch $branch) use ($openShifts, $closedShifts, $timezone): array {
                $openShift = $openShifts->get($branch->id);
                $closedShift = $closedShifts->get($branch->id);
                $isOpen = $openShift !== null;
                $shift = $isOpen ? $openShift : $closedShift;
                $occurredAt = $isOpen ? $shift?->opened_at : $shift?->closed_at;

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'href' => route('pos.index', $branch),
                    'status' => $isOpen ? 'Open' : 'Close',
                    'is_open' => $isOpen,
                    'date' => $occurredAt?->timezone($timezone)->format('M j, Y g:i A'),
                    'user' => $isOpen ? $shift?->opened_by_name : $shift?->closed_by_name,
                ];
            })
            ->values()
            ->all();
    }
}
