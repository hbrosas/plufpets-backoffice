<?php

namespace App\Http\Controllers;

use App\Support\PosLaunchPads;
use Inertia\Inertia;
use Inertia\Response;

class PosLaunchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pos/Launch', [
            'branches' => PosLaunchPads::branches(),
        ]);
    }
}
