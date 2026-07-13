<?php

namespace App\Services;

use App\Models\LicenseKey;
use App\Models\LicenseLog;
use Illuminate\Support\Carbon;

class AnomalyDetector
{
    /**
     * Heuristique simple (§7.3.1) : une même clé vue depuis trop d'IP
     * distinctes sur une fenêtre courte est un signe probable de fuite ou
     * de partage. Pas de géolocalisation (nécessiterait une base tierce) :
     * seul le nombre d'IP distinctes est observé.
     *
     * N'agit qu'après un événement réussi (activate/validate), et au plus
     * une fois par fenêtre pour éviter de spammer le journal.
     */
    public function inspect(LicenseKey $key, string $ip): void
    {
        $threshold = (int) config('license.anomaly.distinct_ip_threshold');
        $windowHours = (int) config('license.anomaly.window_hours');

        if ($threshold <= 0) {
            return;
        }

        $since = Carbon::now()->subHours($windowHours);

        $distinctIps = LicenseLog::query()
            ->where('license_key_id', $key->id)
            ->where('success', true)
            ->where('created_at', '>=', $since)
            ->whereNotNull('ip')
            ->distinct()
            ->count('ip');

        if ($distinctIps < $threshold) {
            return;
        }

        $alreadyFlagged = LicenseLog::query()
            ->where('license_key_id', $key->id)
            ->where('event', 'anomaly_detected')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($alreadyFlagged) {
            return;
        }

        $autoRevoke = (bool) config('license.anomaly.auto_revoke');

        LicenseLog::query()->create([
            'license_key_id' => $key->id,
            'event' => 'anomaly_detected',
            'ip' => $ip,
            'success' => false,
            'reason' => 'distinct_ip_threshold_exceeded',
            'meta' => [
                'distinct_ips' => $distinctIps,
                'threshold' => $threshold,
                'window_hours' => $windowHours,
                'auto_revoked' => $autoRevoke,
            ],
        ]);

        if ($autoRevoke && ! $key->revoked) {
            $key->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => "Révocation automatique : anomalie détectée ({$distinctIps} IP distinctes en {$windowHours}h).",
            ]);
        }
    }
}
