<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PosDrawerController extends Controller
{
    public function show(): View
    {
        return view('pos.drawer');
    }
}
