<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège /api/documentation et /docs/api-docs.json (§audit sécurité, point 1) :
 * ces routes exposent la totalité des endpoints admin et leurs schémas.
 * Double barrière : liste blanche d'IP (même config que RestrictAdminByIp,
 * vide = pas de restriction) et Basic Auth dédiée (identifiants absents =
 * accès refusé, jamais ouvert par défaut contrairement à l'IP allowlist).
 */
class RestrictApiDocs
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('license.admin_allowed_ips');

        if (! empty($allowedIps) && ! IpUtils::checkIp((string) $request->ip(), $allowedIps)) {
            abort(403, 'Accès refusé depuis cette adresse IP.');
        }

        $username = config('license.docs.username');
        $password = config('license.docs.password');

        if (empty($username) || empty($password)) {
            abort(404);
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();

        $validUser = hash_equals((string) $username, $providedUser);
        $validPass = hash_equals((string) $password, $providedPass);

        if (! $validUser || ! $validPass) {
            return response('Authentification requise.', 401, [
                'WWW-Authenticate' => 'Basic realm="API Licences - Documentation"',
            ]);
        }

        return $next($request);
    }
}
