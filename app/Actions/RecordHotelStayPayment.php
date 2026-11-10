<?php

namespace App\Actions;

use App\Enums\PosCashMovementType;
use App\Models\HotelStay;
use App\Models\HotelStayPayment;
use App\Models\PaymentMethod;
use App\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordHotelStayPayment
{
    public function __construct(private RecordPosCashMovement $recordPosCashMovement) {}

    /**
     * @param  array{employee_number: string, name: string}  $employee
     * @param  array{payment_method_id: int, amount: mixed, amount_tendered?: mixed, reference_number?: string|null}  $payment
     */
    public function handle(PosShift $shift, HotelStay $stay, array $payment, array $employee): HotelStayPayment
    {
        return DB::transaction(function () use ($shift, $stay, $payment, $employee): HotelStayPayment {
            $shift = PosShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'status' => 'Open a shift before recording a payment.',
                ]);
            }

            $stay = HotelStay::query()->whereKey($stay->id)->lockForUpdate()->firstOrFail();

            if (! $stay->isCheckedIn()) {
                throw ValidationException::withMessages([
                    'status' => 'This guest is already checked out.',
                ]);
            }

            if ($stay->branch_id !== $shift->branch_id) {
                throw ValidationException::withMessages([
                    'status' => 'Check this guest in at this branch first.',
                ]);
            }

            $prepared = $this->preparedPayment($payment);
            $movementId = null;

            if ($prepared['is_cash']) {
                $movement = $this->recordPosCashMovement->handle(
                    $shift,
                    PosCashMovementType::CashIn,
                    $prepared['amount'],
                    mb_substr('Hotel downpayment for '.$stay->client_name, 0, 255),
                    $employee,
                );
                $movementId = $movement->id;
            }

            return HotelStayPayment::query()->create([
                'hotel_stay_id' => $stay->id,
                'pos_shift_id' => $shift->id,
                'pos_cash_movement_id' => $movementId,
                'payment_method_id' => $prepared['payment_method_id'],
                'payment_method_name' => $prepared['payment_method_name'],
                'is_cash' => $prepared['is_cash'],
                'amount' => $prepared['amount'],
                'amount_tendered' => $prepared['amount_tendered'],
                'change_due' => $prepared['change_due'],
                'reference_number' => $prepared['reference_number'],
                'recorded_by_employee_number' => $employee['employee_number'],
                'recorded_by_name' => $employee['name'],
                'recorded_at' => now(),
            ]);
        });
    }

    /**
     * @param  array{payment_method_id: int, amount: mixed, amount_tendered?: mixed, reference_number?: string|null}  $payment
     * @return array{payment_method_id: int, payment_method_name: string, is_cash: bool, amount: string, amount_tendered: string, change_due: string, reference_number: string|null}
     */
    private function preparedPayment(array $payment): array
    {
        $method = PaymentMethod::query()->findOrFail($payment['payment_method_id']);

        if (! $method->is_active) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Choose an active payment method.',
            ]);
        }

        $amount = round((float) $payment['amount'], 2);

        if ($amount < 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a payment amount.',
            ]);
        }

        $tendered = $payment['amount_tendered'] ?? null;
        $reference = trim((string) ($payment['reference_number'] ?? ''));
        $changeDue = 0.0;

        if ($method->is_cash || $method->asks_for_change) {
            $tenderedMessage = $method->asks_for_change
                ? 'Enter the amount to change for.'
                : 'Enter the amount tendered.';
            $shortMessage = $method->asks_for_change
                ? 'Change for must cover the payment.'
                : 'Amount tendered is less than the payment.';

            if ($tendered === null || $tendered === '') {
                throw ValidationException::withMessages([
                    'amount_tendered' => $tenderedMessage,
                ]);
            }

            if ((float) $tendered + 0.005 < $amount) {
                throw ValidationException::withMessages([
                    'amount_tendered' => $shortMessage,
                ]);
            }

            $changeDue = round((float) $tendered - $amount, 2);
        }

        if (! $method->is_cash) {
            if ($reference === '') {
                throw ValidationException::withMessages([
                    'reference_number' => 'Enter the reference number.',
                ]);
            }

            if (! $method->asks_for_change) {
                $tendered = $amount;
            }
        }

        return [
            'payment_method_id' => $method->id,
            'payment_method_name' => $method->name,
            'is_cash' => $method->is_cash,
            'amount' => number_format($amount, 2, '.', ''),
            'amount_tendered' => number_format((float) $tendered, 2, '.', ''),
            'change_due' => number_format($changeDue, 2, '.', ''),
            'reference_number' => $method->is_cash ? null : $reference,
        ];
    }
}
