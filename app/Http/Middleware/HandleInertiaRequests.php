<?php

namespace App\Http\Middleware;

use App\Models\PosCategory;
use App\Support\EmployeeSession;
use App\Support\PosLaunchPads;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $onAdminShell = $request->is('backoffice/*')
            && ! $request->is('backoffice/login')
            && ! $request->is('backoffice/logout')
            && ! $request->is('backoffice/pos/register/*');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'employee' => EmployeeSession::current(),
            'posBranches' => EmployeeSession::check() && $onAdminShell ? PosLaunchPads::branches() : [],
            'posCategories' => EmployeeSession::check() && $onAdminShell
                ? PosCategory::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                : [],
            'flash' => [
                'status' => $request->session()->get('status'),
                'status_at' => $request->session()->has('status') ? microtime(true) : null,
                'import_errors' => $request->session()->get('import_errors'),
                'created_category' => $request->session()->get('created_category'),
                'created_brand' => $request->session()->get('created_brand'),
                'new_variant' => $request->session()->get('new_variant'),
                'open_drawer' => $request->session()->get('open_drawer'),
                'checkout_success' => $request->session()->get('checkout_success'),
            ],
        ];
    }
}
