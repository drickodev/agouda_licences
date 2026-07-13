<?php

namespace App\Http\Middleware;

use App\Services\LicenseSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAppToken
{
    public function __construct(private readonly LicenseSigner $signer)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('license.app_token');
        $provided = $request->header('X-App-Token', '');

        if (empty($expected) || ! hash_equals((string) $expected, (string) $provided)) {
            $signed = $this->signer->sign([
                'valid' => false,
                'reason' => 'invalid_token',
                'issued_at' => now()->toIso8601String(),
            ]);

            return response()->json($signed, 401);
        }

        return $next($request);
    }
}
