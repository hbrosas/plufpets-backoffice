<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! config('vercel.active')) {
            return;
        }

        $storage = '/tmp/storage';

        foreach ([
            $storage.'/app/public',
            $storage.'/framework/cache/data',
            $storage.'/framework/sessions',
            $storage.'/framework/views',
            $storage.'/logs',
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $this->app->useStoragePath($storage);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('vercel.active')) {
            TrustProxies::at('*');
        }

        RateLimiter::for('login', function (Request $request) {
            $employeeNumber = Str::transliterate(
                Str::lower($request->string('employee_number')->trim()->toString()),
            );

            return Limit::perMinute(5)->by($employeeNumber.'|'.$request->ip());
        });
    }
}
