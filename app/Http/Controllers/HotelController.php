<?php

namespace App\Http\Controllers;

use App\Actions\CheckInHotelGuest;
use App\Actions\CheckOutHotelGuest;
use App\Actions\RecordHotelStayPayment;
use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use App\Http\Requests\StoreBackofficeHotelStayRequest;
use App\Http\Requests\StoreHotelStayPaymentRequest;
use App\Models\Branch;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\PaymentMethod;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Hotel/Index', [
            'stays' => HotelStay::query()
                ->with(['pets', 'payments', 'sales', 'podType', 'branch', 'guestCart'])
                ->checkedIn()
                ->orderByDesc('checked_in_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (HotelStay $stay): array => $stay->toRegisterPayload())
                ->all(),
            'options' => [
                'pod_types' => HotelPodType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn (HotelPodType $podType): array => [
                        'id' => $podType->id,
                        'name' => $podType->name,
                    ])
                    ->all(),
                'genders' => HotelPetGender::options(),
                'vaccines' => HotelVaccine::options(),
            ],
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                ])
                ->all(),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (PaymentMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'is_cash' => $method->is_cash,
                    'asks_for_change' => $method->asks_for_change,
                ])
                ->all(),
            'podTypes' => HotelPodType::query()
                ->withCount('stays')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (HotelPodType $podType): array => [
                    'id' => $podType->id,
                    'name' => $podType->name,
                    'is_active' => $podType->is_active,
                    'stay_count' => $podType->stays_count,
                ])
                ->all(),
        ]);
    }

    public function store(StoreBackofficeHotelStayRequest $request, CheckInHotelGuest $checkInHotelGuest): RedirectResponse
    {
        $branch = Branch::query()->whereKey($request->integer('branch_id'))->where('is_active', true)->first();

        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => 'Choose a branch.',
            ]);
        }

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

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Checked in '.$stay->client_name.'.');
    }

    public function storePayment(
        StoreHotelStayPaymentRequest $request,
        HotelStay $hotelStay,
        RecordHotelStayPayment $recordHotelStayPayment,
    ): RedirectResponse {
        $branch = $this->stayBranch($hotelStay);
        $employee = EmployeeSession::current();
        $recordHotelStayPayment->handle(
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

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Payment recorded.');
    }

    public function checkout(HotelStay $hotelStay, CheckOutHotelGuest $checkOutHotelGuest): RedirectResponse
    {
        $branch = $this->stayBranch($hotelStay);
        $this->openShift($branch, 'Open a shift before checking a guest out.');
        $employee = EmployeeSession::current();
        $stay = $checkOutHotelGuest->handle(
            $hotelStay,
            $employee ?? ['employee_number' => '', 'name' => ''],
        );

        return redirect()
            ->route('hotel.index')
            ->with('status', 'Checked out '.$stay->client_name.'.');
    }

    private function stayBranch(HotelStay $stay): Branch
    {
        $branch = Branch::query()->whereKey($stay->branch_id)->where('is_active', true)->first();

        abort_unless($branch instanceof Branch, 404);

        return $branch;
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
