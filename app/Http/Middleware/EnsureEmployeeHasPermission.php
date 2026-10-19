<?php

namespace App\Http\Middleware;

use App\Support\EmployeeSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeHasPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(EmployeeSession::allows($permission), 403);

        return $next($request);
    }
}
