<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use Inertia\Inertia;
use Inertia\Response;

class PosSmsHistoryController extends Controller
{
    public function index(): Response
    {
        $messages = SmsMessage::query()
            ->with(['branch:id,name'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SmsMessage $message): array => $message->toHistoryArray());

        return Inertia::render('Pos/SmsHistory/Index', [
            'messages' => $messages,
        ]);
    }
}
