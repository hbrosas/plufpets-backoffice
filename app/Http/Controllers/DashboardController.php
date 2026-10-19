<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'activeStaffCount' => Employee::query()->where('is_active', true)->count(),
        ]);
    }
}
