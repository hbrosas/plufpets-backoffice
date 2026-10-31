<?php

namespace App\Http\Controllers;

use App\Enums\HolidayType;
use App\Http\Requests\ImportHolidaysRequest;
use App\Http\Requests\StoreHolidayRequest;
use App\Models\Holiday;
use App\Support\PhilippineHolidays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->query('year', now()->year);

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        return Inertia::render('Staff/Holidays/Index', [
            'year' => $year,
            'holidays' => Holiday::query()
                ->whereYear('date', $year)
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->toDateString(),
                    'name' => $holiday->name,
                    'type' => $holiday->type->value,
                    'type_label' => $holiday->type->label(),
                    'worked_label' => $holiday->type->workedLabel(),
                    'unworked_label' => $holiday->type->unworkedLabel(),
                ]),
            'types' => array_map(fn (HolidayType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'worked_label' => $type->workedLabel(),
                'unworked_label' => $type->unworkedLabel(),
            ], HolidayType::cases()),
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::query()->create($request->safe()->only(['date', 'name', 'type']));

        return redirect()
            ->route('staff.holidays.index', ['year' => substr((string) $request->validated('date'), 0, 4)])
            ->with('status', 'Holiday added.');
    }

    public function import(ImportHolidaysRequest $request): RedirectResponse
    {
        $year = (int) $request->validated('year');
        $created = 0;
        $skipped = 0;

        foreach (PhilippineHolidays::forYear($year) as $holiday) {
            $exists = Holiday::query()->whereDate('date', $holiday['date'])->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Holiday::query()->create([
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'type' => $holiday['type'],
            ]);
            $created++;
        }

        $status = "Imported {$created} Philippine holidays for {$year}.";

        if ($skipped > 0) {
            $status .= " {$skipped} ".($skipped === 1 ? 'date was' : 'dates were').' already on the calendar.';
        }

        return redirect()
            ->route('staff.holidays.index', ['year' => $year])
            ->with('status', $status);
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $year = $holiday->date->year;
        $holiday->delete();

        return redirect()
            ->route('staff.holidays.index', ['year' => $year])
            ->with('status', 'Holiday deleted.');
    }
}
