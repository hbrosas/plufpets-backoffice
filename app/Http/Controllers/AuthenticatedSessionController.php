<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $employee = $request->attributes->get('authenticatedEmployee');

        if (! $employee instanceof Employee) {
            abort(403);
        }

        $request->session()->regenerate();

        $request->session()->put('employee', [
            'id' => $employee->id,
        ]);

        return redirect()->intended($employee->homeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
