<?php

namespace App\Providers;

use App\Models\LicenseLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        // Endpoints sensibles (activate, validate, deactivate) : X-App-Token
        // étant partagé par tous les clients (§audit sécurité, point 2), la
        // limite porte à la fois sur l'IP et sur l'empreinte machine, pour
        // freiner aussi bien un abus depuis une seule IP qu'un token utilisé
        // en masse depuis des machines différentes.
        RateLimiter::for('license', function (Request $request) {
            return [
                Limit::perMinute(10)->by('ip:'.$request->ip())->response($this->throttleResponder('ip')),
                Limit::perMinute(10)->by('fingerprint:'.$this->fingerprint($request))->response($this->throttleResponder('machine_fingerprint')),
            ];
        });

        // Déblocage de compte local : code court à usage unique, limite plus
        // stricte pour freiner tout essai par force brute.
        RateLimiter::for('account-recovery', function (Request $request) {
            return [
                Limit::perMinute(5)->by('ip:'.$request->ip())->response($this->throttleResponder('ip')),
                Limit::perMinute(5)->by('fingerprint:'.$this->fingerprint($request))->response($this->throttleResponder('machine_fingerprint')),
            ];
        });

        // Reveal (§audit sécurité, point 3) : action la plus sensible du
        // panel admin (renvoie une clé en clair), throttle dédié par compte
        // admin, plus strict que le reste du groupe /admin.
        RateLimiter::for('license-key-reveal', function (Request $request) {
            return Limit::perHour(5)->by('admin:'.$request->user()?->id);
        });
    }

    private function fingerprint(Request $request): string
    {
        return (string) ($request->input('machine_fingerprint') ?? 'unknown');
    }

    private function throttleResponder(string $by): \Closure
    {
        return function (Request $request, array $headers) use ($by) {
            Log::warning('Rate limit exceeded on license endpoint', [
                'by' => $by,
                'ip' => $request->ip(),
                'machine_fingerprint' => $request->input('machine_fingerprint'),
                'path' => $request->path(),
            ]);

            LicenseLog::query()->create([
                'license_key_id' => null,
                'event' => 'rate_limited',
                'machine_fingerprint' => $request->input('machine_fingerprint'),
                'ip' => $request->ip(),
                'success' => false,
                'reason' => "throttled_by_{$by}",
                'meta' => ['path' => $request->path()],
            ]);

            return response()->json([
                'message' => 'Trop de requêtes, réessayez plus tard.',
            ], 429, $headers);
        };
    }
}
