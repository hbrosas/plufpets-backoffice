<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSmsTemplateRequest;
use App\Models\SmsTemplate;
use Illuminate\Http\JsonResponse;

class PosSmsTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = SmsTemplate::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'body']);

        return response()->json(['templates' => $templates]);
    }

    public function store(StoreSmsTemplateRequest $request): JsonResponse
    {
        $template = SmsTemplate::query()->create($request->safe()->only(['name', 'body']));

        return response()->json([
            'status' => 'Template saved.',
            'template' => $template->only(['id', 'name', 'body']),
        ], 201);
    }

    public function update(StoreSmsTemplateRequest $request, SmsTemplate $smsTemplate): JsonResponse
    {
        $smsTemplate->update($request->safe()->only(['name', 'body']));

        return response()->json([
            'status' => 'Template updated.',
            'template' => $smsTemplate->only(['id', 'name', 'body']),
        ]);
    }

    public function destroy(SmsTemplate $smsTemplate): JsonResponse
    {
        $smsTemplate->delete();

        return response()->json(['status' => 'Template deleted.']);
    }
}
