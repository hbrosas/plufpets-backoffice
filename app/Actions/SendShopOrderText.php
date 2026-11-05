<?php

namespace App\Actions;

use App\Enums\ShopOrderStatus;
use App\Models\Branch;
use App\Models\PosOpenOrder;
use App\Support\EmployeeSession;
use App\Support\ShopOrderMessages;
use Illuminate\Validation\ValidationException;

class SendShopOrderText
{
    public function __construct(private SendPosSms $sendPosSms) {}

    public function handle(PosOpenOrder $order): ?string
    {
        $status = ShopOrderStatus::tryFrom((string) $order->status);
        $branch = $order->branch;

        if (! $status instanceof ShopOrderStatus || ! is_string($order->phone) || $order->phone === '' || ! $branch instanceof Branch) {
            return null;
        }

        $order->loadMissing('branch:id,name');
        $employee = EmployeeSession::current();

        try {
            $this->sendPosSms->handle(
                $branch,
                $order->phone,
                ShopOrderMessages::render($order, $status),
                [
                    'employee_number' => $employee['employee_number'] ?? '',
                    'name' => $employee['name'] ?? 'Shop',
                ],
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return is_string($message) && $message !== '' ? $message : 'Could not send the text.';
        }

        return null;
    }
}
