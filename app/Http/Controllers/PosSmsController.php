<?php

namespace App\Http\Controllers;

use App\Actions\SendPosSms;
use App\Http\Requests\StorePosSmsRequest;
use App\Models\Branch;
use App\Models\SmsMessage;
use App\Support\EmployeeSession;
use Illuminate\Http\JsonResponse;

class PosSmsController extends Controller
{
    public function store(StorePosSmsRequest $request, Branch $branch, SendPosSms $sendPosSms): JsonResponse
    {
        abort_unless($branch->is_active, 404);

        $sendPosSms->handle(
            $branch,
            $request->validated('number'),
            $request->validated('message'),
            EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''],
        );

        return response()->json(['status' => 'Text sent.']);
    }

    public function history(): JsonResponse
    {
        $messages = SmsMessage::query()
            ->with(['branch:id,name'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SmsMessage $message): array => [
                'id' => $message->id,
                'number' => $message->number,
                'body' => $message->body,
                'branch' => $message->branch?->name,
                'sender' => $message->employee_name.' · #'.$message->employee_number,
                'sent_at' => $message->sent_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            ]);

        return response()->json(['messages' => $messages]);
    }
}
