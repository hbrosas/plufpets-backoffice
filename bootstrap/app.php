<?php

use App\Http\Middleware\EnsureEmployeeHasPermission;
use App\Http\Middleware\EnsureEmployeeIsAuthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfEmployeeAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(['XSRF-TOKEN']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'employee.auth' => EnsureEmployeeIsAuthenticated::class,
            'employee.guest' => RedirectIfEmployeeAuthenticated::class,
            'employee.permission' => EnsureEmployeeHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
