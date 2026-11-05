<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSmsTemplateRequest;
use App\Models\SmsTemplate;
use App\Support\ShopOrderMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosSmsTemplateController extends Controller
{
    public function manage(): Response
    {
        return Inertia::render('Sms/Templates/Index', [
            'templates' => SmsTemplate::query()
                ->whereNull('key')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'body']),
            'deliveryTemplates' => ShopOrderMessages::editorPayload(),
        ]);
    }

    public function index(): JsonResponse
    {
        $templates = SmsTemplate::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'body', 'key']);

        return response()->json(['templates' => $templates]);
    }

    public function store(StoreSmsTemplateRequest $request): JsonResponse|RedirectResponse
    {
        $template = SmsTemplate::query()->create($request->safe()->only(['name', 'body']));

        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => 'Template saved.',
                'template' => $template->only(['id', 'name', 'body']),
            ], 201);
        }

        return back()->with('status', 'Template saved.');
    }

    public function update(StoreSmsTemplateRequest $request, SmsTemplate $smsTemplate): JsonResponse|RedirectResponse
    {
        abort_if($this->isDeliveryTemplate($smsTemplate) && ! $this->wantsJson($request), 404);

        $smsTemplate->update($request->safe()->only(['name', 'body']));

        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => 'Template updated.',
                'template' => $smsTemplate->only(['id', 'name', 'body']),
            ]);
        }

        return back()->with('status', 'Template updated.');
    }

    public function destroy(Request $request, SmsTemplate $smsTemplate): JsonResponse|RedirectResponse
    {
        if ($this->isDeliveryTemplate($smsTemplate)) {
            $message = 'Delivery templates are edited in SMS Templates.';

            if ($this->wantsJson($request)) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return back()->withErrors(['template' => $message]);
        }

        $smsTemplate->delete();

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 'Template deleted.']);
        }

        return back()->with('status', 'Template deleted.');
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() && ! $request->header('X-Inertia');
    }

    private function isDeliveryTemplate(SmsTemplate $smsTemplate): bool
    {
        return is_string($smsTemplate->key) && $smsTemplate->key !== '';
    }
}
