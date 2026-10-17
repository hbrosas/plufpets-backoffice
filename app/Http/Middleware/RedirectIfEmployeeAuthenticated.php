<?php

namespace App\Http\Middleware;

use App\Support\EmployeeSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfEmployeeAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (EmployeeSession::check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
