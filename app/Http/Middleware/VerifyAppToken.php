<?php

namespace App\Http\Middleware;

use App\Services\AppTokenAbuseDetector;
use App\Services\LicenseSigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TODO (§audit sécurité, point 2) : X-App-Token est un secret unique
 * partagé par tous les clients distribués — considéré comme potentiellement
 * public puisqu'il est embarqué dans le logiciel. Migration cible : un
 * secret par client, dérivé à l'activation, avec chaque requête signée par
 * HMAC (clé dérivée) plutôt qu'un simple header statique comparé. Non fait
 * ici : nécessite de revoir le provisioning côté client distribué. En
 * attendant, défense en profondeur via throttle par IP+empreinte
 * (AppServiceProvider) et AppTokenAbuseDetector ci-dessous.
 */
class VerifyAppToken
{
    public function __construct(
        private readonly LicenseSigner $signer,
        private readonly AppTokenAbuseDetector $abuseDetector,
    ) {
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

        $this->abuseDetector->inspect($request);

        return $next($request);
    }
}
