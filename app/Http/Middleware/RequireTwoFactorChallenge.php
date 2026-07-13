<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TwoFactorChallenge;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorChallenge
{
    /**
     * Force la saisie d'un code TOTP à chaque nouvelle session pour les
     * comptes ayant activé le 2FA (§7.3.4). N'impose rien tant que
     * l'administrateur n'a pas volontairement activé le 2FA depuis la page
     * de sécurité, pour éviter tout risque de verrouillage accidentel.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (session('two_factor_verified_user_id') === $user->id) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.two-factor-challenge')
            || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        return redirect(TwoFactorChallenge::getUrl());
    }
}
