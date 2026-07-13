<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Endpoints sensibles (activate, validate) : limite par IP, durcissable par clé.
        RateLimiter::for('license', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Déblocage de compte local : code court à usage unique, limite plus
        // stricte pour freiner tout essai par force brute.
        RateLimiter::for('account-recovery', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
