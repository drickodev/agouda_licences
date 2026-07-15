<?php

namespace App\Services;

use App\Models\LicenseLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * X-App-Token étant un secret unique partagé par tous les clients
 * distribués (§audit sécurité, point 2), une fuite se traduit typiquement
 * par un afflux anormal d'empreintes machine nouvelles sur une fenêtre
 * courte. Heuristique globale volontairement simple, dans le même esprit
 * que AnomalyDetector (qui, lui, raisonne par clé de licence) : pas de
 * blocage automatique, seulement une alerte journalisée pour investigation.
 */
class AppTokenAbuseDetector
{
    public function inspect(Request $request): void
    {
        $threshold = (int) config('license.token_abuse.distinct_fingerprint_threshold');
        $windowMinutes = (int) config('license.token_abuse.window_minutes');

        if ($threshold <= 0) {
            return;
        }

        $since = Carbon::now()->subMinutes($windowMinutes);

        $distinctFingerprints = LicenseLog::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('machine_fingerprint')
            ->distinct()
            ->count('machine_fingerprint');

        if ($distinctFingerprints < $threshold) {
            return;
        }

        $alreadyFlagged = LicenseLog::query()
            ->where('event', 'app_token_abuse_suspected')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($alreadyFlagged) {
            return;
        }

        LicenseLog::query()->create([
            'license_key_id' => null,
            'event' => 'app_token_abuse_suspected',
            'ip' => $request->ip(),
            'success' => false,
            'reason' => 'distinct_fingerprint_threshold_exceeded',
            'meta' => [
                'distinct_fingerprints' => $distinctFingerprints,
                'threshold' => $threshold,
                'window_minutes' => $windowMinutes,
            ],
        ]);
    }
}
