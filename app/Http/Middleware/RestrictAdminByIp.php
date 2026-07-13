<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictAdminByIp
{
    /**
     * Restreint l'accès au panel admin à une liste blanche d'IP/CIDR
     * (§7.3.4). Liste vide = pas de restriction (config par défaut, adaptée
     * au développement ; à renseigner en production).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('license.admin_allowed_ips');

        if (empty($allowed)) {
            return $next($request);
        }

        if (! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403, 'Accès refusé depuis cette adresse IP.');
        }

        return $next($request);
    }
}
