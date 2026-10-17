<?php

namespace App\Http\Middleware;

use App\Support\EmployeeSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeIsAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! EmployeeSession::check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
