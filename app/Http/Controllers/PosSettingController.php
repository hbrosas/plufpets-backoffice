<?php

namespace App\Http\Controllers;

use App\Enums\PrinterConnection;
use App\Enums\PrinterRole;
use App\Http\Requests\UpdateShopOrderTextRequest;
use App\Models\Branch;
use App\Models\Printer;
use App\Models\SmsTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PosSettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pos/Settings/Index', [
            'printers' => Printer::query()
                ->with('branch:id,name')
                ->orderBy('branch_id')
                ->orderBy('role')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Printer $printer): array => $printer->toSettingsPayload())
                ->values(),
            'branches' => Branch::query()
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name']),
            'printerRoles' => array_map(
                fn (PrinterRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                PrinterRole::cases(),
            ),
            'printerConnections' => array_map(
                fn (PrinterConnection $connection): array => [
                    'value' => $connection->value,
                    'label' => $connection->label(),
                ],
                PrinterConnection::cases(),
            ),
        ]);
    }

    public function updateOrderText(UpdateShopOrderTextRequest $request, SmsTemplate $smsTemplate): RedirectResponse
    {
        abort_unless(is_string($smsTemplate->key) && str_starts_with($smsTemplate->key, 'shop.'), 404);

        $smsTemplate->update($request->safe()->only(['body']));

        return redirect()
            ->route('sms.templates.index')
            ->with('status', 'Text message saved.');
    }
}
