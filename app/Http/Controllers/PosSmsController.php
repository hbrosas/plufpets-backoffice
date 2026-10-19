<?php

namespace App\Http\Controllers;

use App\Actions\SendPosSms;
use App\Http\Requests\StorePosSmsRequest;
use App\Models\Branch;
use App\Models\PosShift;
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

    public function resend(SmsMessage $smsMessage, SendPosSms $sendPosSms): JsonResponse
    {
        $branch = $smsMessage->branch;
        abort_unless($branch instanceof Branch && $branch->is_active, 404);

        $sendPosSms->handle(
            $branch,
            $smsMessage->number,
            $smsMessage->body,
            EmployeeSession::current() ?? ['employee_number' => '', 'name' => ''],
        );

        return response()->json(['status' => 'Text resent.']);
    }

    public function history(Branch $branch): JsonResponse
    {
        abort_unless($branch->is_active, 404);

        $shift = PosShift::query()
            ->where('branch_id', $branch->id)
            ->open()
            ->first();

        if (! $shift instanceof PosShift) {
            return response()->json(['messages' => []]);
        }

        $messages = SmsMessage::query()
            ->with(['branch:id,name'])
            ->where('branch_id', $branch->id)
            ->where('sent_at', '>=', $shift->opened_at)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (SmsMessage $message): array => $message->toHistoryArray());

        return response()->json(['messages' => $messages]);
    }
}
