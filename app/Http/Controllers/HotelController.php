<?php

namespace App\Http\Controllers;

use App\Actions\CheckInHotelGuest;
use App\Actions\CheckOutHotelGuest;
use App\Actions\RecordHotelStayPayment;
use App\Actions\UpdateHotelStayDates;
use App\Enums\HotelPetGender;
use App\Enums\HotelVaccine;
use App\Http\Requests\StoreBackofficeHotelStayRequest;
use App\Http\Requests\StoreHotelStayPaymentRequest;
use App\Http\Requests\UpdateHotelStayDatesRequest;
use App\Models\Branch;
use App\Models\HotelPodType;
use App\Models\HotelStay;
use App\Models\PaymentMethod;
use App\Models\PosShift;
use App\Support\EmployeeSession;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === 'checked-out' ? 'checked-out' : 'current';
        $from = $this->filterDate($request->string('from')->toString());
        $to = $this->filterDate($request->string('to')->toString());

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $current = fn (): array => $this->stayList(
            HotelStay::query()->checkedIn()->orderByDesc('checked_in_at')->orderByDesc('id'),
        );
        $checkedOut = fn (): array => $this->stayList(
            HotelStay::query()
                ->checkedOut()
                ->when($from !== null, fn (Builder $query): Builder => $query->where(
                    'checked_out_at',
                    '>=',
                    CarbonImmutable::parse($from, ReportPeriod::ZONE)->startOfDay()->utc(),
                ))
                ->when($to !== null, fn (Builder $query): Builder => $query->where(
                    'checked_out_at',
                    '<=',
                    CarbonImmutable::parse($to, ReportPeriod::ZONE)->endOfDay()->utc(),
                ))
                ->orderByDesc('checked_out_at')
                ->orderByDesc('id'),
        );

        return Inertia::render('Hotel/Index', [
            'stays' => $tab === 'current' ? $current : Inertia::optional($current),
            'checkedOutStays' => $tab === 'checked-out' ? $checkedOut : Inertia::optional($checkedOut),
            'filters' => [
                'tab' => $tab,
                'from' => $from ?? '',
                'to' => $to ?? '',
            ],
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

    public function update(
        UpdateHotelStayDatesRequest $request,
        HotelStay $hotelStay,
        UpdateHotelStayDates $updateHotelStayDates,
    ): RedirectResponse {
        $updateHotelStayDates->handle($hotelStay, [
            'checked_in_at' => $request->validated('checked_in_at'),
            'checkout_on' => $request->validated('checkout_on'),
            'checked_out_at' => $request->validated('checked_out_at'),
        ]);

        $tab = $request->string('tab')->toString() === 'checked-out' ? 'checked-out' : 'current';
        $from = $this->filterDate($request->string('from')->toString());
        $to = $this->filterDate($request->string('to')->toString());

        return redirect()
            ->route('hotel.index', array_filter([
                'tab' => $tab === 'checked-out' ? $tab : null,
                'from' => $tab === 'checked-out' ? $from : null,
                'to' => $tab === 'checked-out' ? $to : null,
            ]))
            ->with('status', 'Stay dates updated.');
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

    /**
     * @param  Builder<HotelStay>  $query
     * @return list<array<string, mixed>>
     */
    private function stayList(Builder $query): array
    {
        return $query
            ->with(['pets', 'payments', 'sales', 'podType', 'branch', 'guestCart'])
            ->get()
            ->map(fn (HotelStay $stay): array => $stay->toRegisterPayload())
            ->all();
    }

    private function filterDate(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, ReportPeriod::ZONE);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
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
