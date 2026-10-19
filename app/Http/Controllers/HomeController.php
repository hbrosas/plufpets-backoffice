<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Site/Home', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'address', 'phone']),
        ]);
    }
}
