<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\TestingMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalBranch
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch');

        abort_if(
            $branch instanceof Branch && $branch->for_test_use && ! TestingMode::includesTestData(),
            404,
        );

        return $next($request);
    }
}
