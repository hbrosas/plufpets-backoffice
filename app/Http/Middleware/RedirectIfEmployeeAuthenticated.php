<?php

namespace App\Http\Middleware;

use App\Models\Employee;
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
            $employeeId = EmployeeSession::current()['id'] ?? null;
            $employee = $employeeId === null ? null : Employee::query()->find($employeeId);

            if ($employee instanceof Employee) {
                return redirect()->to($employee->homeUrl());
            }

            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
