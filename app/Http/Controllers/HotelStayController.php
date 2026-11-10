<?php

namespace App\Http\Controllers;

use App\Actions\CheckInHotelGuest;
use App\Actions\CheckOutHotelGuest;
use App\Actions\RecordHotelStayPayment;
use App\Http\Requests\StoreHotelStayPaymentRequest;
use App\Http\Requests\StoreHotelStayRequest;
use App\Models\Branch;
use App\Models\HotelStay;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class HotelStayController extends Controller
{
    public function store(
        StoreHotelStayRequest $request,
        Branch $branch,
        CheckInHotelGuest $checkInHotelGuest,
        PosController $posController,
    ): RedirectResponse|Response {
        abort_unless($branch->is_active, 404);

        $employee = EmployeeSession::current();
        $stay = $checkInHotelGuest->handle(
            $this->openShift($branch, 'Open a shift before checking a guest in.'),
            $request->safe()->only([
                'client_name',
                'contact_number',
                'address',
                'checkout_on',
                'hotel_pod_type_id',
                'pets',
            ]),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Checked in '.$stay->client_name.'.',
        ]);
    }

    public function storePayment(
        StoreHotelStayPaymentRequest $request,
        Branch $branch,
        HotelStay $hotelStay,
        RecordHotelStayPayment $recordHotelStayPayment,
        PosController $posController,
    ): RedirectResponse|Response {
        abort_unless($branch->is_active, 404);
        $this->stayAtBranch($branch, $hotelStay);

        $employee = EmployeeSession::current();
        $payment = $recordHotelStayPayment->handle(
            $this->openShift($branch, 'Open a shift before recording a payment.'),
            $hotelStay,
            $request->safe()->only([
                'payment_method_id',
                'amount',
                'amount_tendered',
                'reference_number',
            ]),
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Downpayment recorded.',
            'open_drawer' => $payment->is_cash,
        ]);
    }

    public function checkout(
        Request $request,
        Branch $branch,
        HotelStay $hotelStay,
        CheckOutHotelGuest $checkOutHotelGuest,
        PosController $posController,
    ): RedirectResponse|Response {
        abort_unless($branch->is_active, 404);
        $this->stayAtBranch($branch, $hotelStay);
        $this->openShift($branch, 'Open a shift before checking a guest out.');

        $employee = EmployeeSession::current();
        $stay = $checkOutHotelGuest->handle(
            $hotelStay,
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return $posController->fromMutation($request, $branch, [
            'status' => 'Checked out '.$stay->client_name.'.',
        ]);
    }

    private function stayAtBranch(Branch $branch, HotelStay $stay): void
    {
        abort_unless($stay->branch_id === $branch->id, 404);
    }

    private function openShift(Branch $branch, string $message): PosShift
    {
        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        if ($shift === null) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }

        return $shift;
    }
}
