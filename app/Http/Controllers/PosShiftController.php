<?php

namespace App\Http\Controllers;

use App\Actions\ClosePosShift;
use App\Actions\OpenPosShift;
use App\Http\Requests\ClosePosShiftRequest;
use App\Http\Requests\StorePosShiftRequest;
use App\Models\Branch;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;

class PosShiftController extends Controller
{
    public function store(StorePosShiftRequest $request, Branch $branch, OpenPosShift $openPosShift): RedirectResponse
    {
        abort_unless($branch->is_active, 404);

        $employee = EmployeeSession::current();

        $openPosShift->handle(
            $branch,
            $request->validated('denominations'),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return redirect()
            ->route('pos.index', $branch)
            ->with('status', 'Shift opened.')
            ->with('open_drawer', true);
    }

    public function close(ClosePosShiftRequest $request, Branch $branch, PosShift $posShift, ClosePosShift $closePosShift): RedirectResponse
    {
        abort_unless($posShift->branch_id === $branch->id, 404);

        $employee = EmployeeSession::current();

        $shift = $closePosShift->handle(
            $posShift,
            $request->validated('denominations'),
            (string) $request->validated('remit_amount'),
            $request->boolean('confirm_variance'),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return redirect()
            ->route('pos.shifts.z-report', [$branch, $shift])
            ->with('status', 'Shift closed.')
            ->with('open_drawer', true);
    }
}
