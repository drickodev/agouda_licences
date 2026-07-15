<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès aux routes sensibles (clés de licence, clients) tant que
 * le compte admin n'a pas activé le 2FA (§audit sécurité, point 4).
 */
class RequireTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasTwoFactorEnabled()) {
            abort(403, "L'authentification à deux facteurs doit être activée pour accéder à cette ressource. "
                .'Activez-la via POST /api/admin/2fa/setup.');
        }

        return $next($request);
    }
}
