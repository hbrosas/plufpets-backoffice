<?php

namespace App\Http\Controllers;

use App\Actions\RecordPosCashMovement;
use App\Enums\PosCashMovementType;
use App\Http\Requests\StorePosCashMovementRequest;
use App\Models\Branch;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class PosCashMovementController extends Controller
{
    public function store(StorePosCashMovementRequest $request, Branch $branch, RecordPosCashMovement $recordPosCashMovement, PosController $posController): RedirectResponse|Response
    {
        $employee = EmployeeSession::current();
        $type = PosCashMovementType::from($request->validated('type'));

        $recordPosCashMovement->handle(
            $this->openShift($branch),
            $type,
            (string) $request->validated('amount'),
            $request->validated('reason'),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return $posController->fromMutation($request, $branch, [
            'status' => $type->label().' recorded.',
            'open_drawer' => true,
        ]);
    }

    private function openShift(Branch $branch): PosShift
    {
        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        if ($shift === null) {
            throw ValidationException::withMessages([
                'status' => 'Open a shift before recording cash in or cash out.',
            ]);
        }

        return $shift;
    }
}
