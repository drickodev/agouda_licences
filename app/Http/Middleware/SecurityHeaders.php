<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP globaux (§audit sécurité, point 6). Appliqué à
 * toutes les réponses : l'hébergement (cPanel/Apache mutualisé, sans accès
 * à la conf du reverse proxy) ne permet pas de les poser autrement.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isDocs = $request->is('api/documentation*', 'docs*', 'api/oauth2-callback');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($isDocs));

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }

    private function contentSecurityPolicy(bool $isDocs): string
    {
        if ($isDocs) {
            // Swagger UI a besoin de charger ses propres assets (servis en
            // local par le package, jamais depuis un CDN) et d'exécuter un
            // peu de JS/CSS inline pour son rendu.
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
            ]);
        }

        return implode('; ', [
            "default-src 'none'",
            "frame-ancestors 'none'",
        ]);
    }
}
