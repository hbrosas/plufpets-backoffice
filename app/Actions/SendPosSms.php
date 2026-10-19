<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\SmsMessage;
use App\Support\SmsDisclaimer;

class SendPosSms
{
    public function __construct(private SendSemaphoreSms $sendSemaphoreSms) {}

    /**
     * @param  array{employee_number: string, name: string}  $employee
     */
    public function handle(Branch $branch, string $number, string $message, array $employee): SmsMessage
    {
        $body = SmsDisclaimer::append($message);

        $this->sendSemaphoreSms->handle($number, $body);

        return SmsMessage::query()->create([
            'branch_id' => $branch->id,
            'number' => $number,
            'body' => $body,
            'employee_number' => $employee['employee_number'],
            'employee_name' => $employee['name'],
            'sent_at' => now(),
        ]);
    }
}
