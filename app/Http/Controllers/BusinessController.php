<?php

namespace App\Http\Controllers;

use App\Actions\ResetAccount;
use App\Http\Requests\ResetAccountRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function edit(): Response
    {
        $business = Business::current();

        return Inertia::render('Business/Edit', [
            'business' => [
                'name' => $business?->name ?? '',
                'address' => $business?->address ?? '',
                'phone' => $business?->phone ?? '',
                'email' => $business?->email ?? '',
                'tin' => $business?->tin ?? '',
                'daily_sales_target' => $business?->daily_sales_target ?? '',
                'logo_url' => $business?->logoUrl(),
                'favicon_url' => $business?->faviconUrl(),
            ],
        ]);
    }

    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = Business::current() ?? new Business;
        $business->fill($request->safe()->only(['name', 'address', 'phone', 'email', 'tin', 'daily_sales_target']));
        $this->syncUpload($business, 'logo', $request->file('logo'), $request->boolean('remove_logo'));
        $this->syncUpload($business, 'favicon', $request->file('favicon'), $request->boolean('remove_favicon'));
        $business->save();

        return back()->with('status', 'Business information saved.');
    }

    public function reset(ResetAccountRequest $request, ResetAccount $resetAccount): RedirectResponse
    {
        $resetAccount->handle();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'The account was reset. Sign in with employee number 10001 and PIN 1234.');
    }

    private function syncUpload(Business $business, string $column, ?UploadedFile $file, bool $remove): void
    {
        $pathColumn = $column.'_path';

        if ($file instanceof UploadedFile) {
            if (filled($business->{$pathColumn})) {
                Storage::disk('public')->delete($business->{$pathColumn});
            }

            $business->{$pathColumn} = $file->store('business', 'public');

            return;
        }

        if ($remove && filled($business->{$pathColumn})) {
            Storage::disk('public')->delete($business->{$pathColumn});
            $business->{$pathColumn} = null;
        }
    }
}
