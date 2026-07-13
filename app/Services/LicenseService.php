<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\LicenseKey;
use App\Models\LicenseLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LicenseService
{
    public function __construct(
        private readonly LicenseSigner $signer,
        private readonly AnomalyDetector $anomalyDetector,
    ) {
    }

    /**
     * @return array{status: int, signed: array}
     */
    public function activate(Request $request, array $data): array
    {
        $key = LicenseKey::query()->wherePlaintextKey($data['key'])->first();

        if (! $key) {
            return $this->refuse($request, null, 'not_found', $data);
        }

        if ($key->revoked) {
            return $this->refuse($request, $key, 'revoked', $data);
        }

        if ($key->isExpired()) {
            return $this->refuse($request, $key, 'expired', $data);
        }

        $product = Product::query()->where('slug', $data['product_slug'])->first();
        if (! $product || $product->id !== $key->product_id) {
            return $this->refuse($request, $key, 'not_found', $data);
        }

        return DB::transaction(function () use ($request, $key, $data) {
            $key->refresh();

            $existing = $key->activationFor($data['machine_fingerprint']);

            if ($existing) {
                $existing->update([
                    'last_seen_at' => now(),
                    'last_ip' => $request->ip(),
                    'machine_name' => $data['machine_name'] ?? $existing->machine_name,
                ]);

                return $this->success($request, $key, $data);
            }

            if (! $key->hasFreeSeat()) {
                return $this->refuse($request, $key, 'seat_limit_reached', $data);
            }

            Activation::query()->create([
                'license_key_id' => $key->id,
                'machine_fingerprint' => $data['machine_fingerprint'],
                'machine_name' => $data['machine_name'] ?? null,
                'status' => 'active',
                'last_ip' => $request->ip(),
                'last_seen_at' => now(),
            ]);

            return $this->success($request, $key, $data);
        });
    }

    /**
     * @return array{status: int, signed: array}
     */
    public function validate(Request $request, array $data): array
    {
        $key = LicenseKey::query()->wherePlaintextKey($data['key'])->first();

        if (! $key) {
            return $this->refuse($request, null, 'not_found', $data);
        }

        if ($key->revoked) {
            return $this->refuse($request, $key, 'revoked', $data);
        }

        if ($key->isExpired()) {
            return $this->refuse($request, $key, 'expired', $data);
        }

        $activation = $key->activationFor($data['machine_fingerprint']);

        if (! $activation) {
            return $this->refuse($request, $key, 'not_activated', $data);
        }

        $activation->update([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return $this->success($request, $key, $data);
    }

    /**
     * @return array{status: int, signed: array}
     */
    public function deactivate(Request $request, array $data): array
    {
        $key = LicenseKey::query()->wherePlaintextKey($data['key'])->first();

        if (! $key) {
            return $this->refuse($request, null, 'not_found', $data);
        }

        $activation = $key->activationFor($data['machine_fingerprint']);

        if (! $activation) {
            return $this->refuse($request, $key, 'not_activated', $data);
        }

        $activation->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        $payload = [
            'valid' => true,
            'released' => true,
            'nonce' => $data['nonce'],
            'machine_fingerprint' => $data['machine_fingerprint'],
            'issued_at' => now()->toIso8601String(),
        ];

        $signed = $this->signer->sign($payload);

        $this->log($key, 'deactivate', $request, $data, true, null);

        return ['status' => 200, 'signed' => $signed];
    }

    private function success(Request $request, LicenseKey $key, array $data): array
    {
        $payload = [
            'valid' => true,
            'product' => $key->product->slug,
            'license_type' => $key->license_type,
            'expires_at' => $key->expires_at?->toIso8601String(),
            'machine_fingerprint' => $data['machine_fingerprint'],
            'nonce' => $data['nonce'],
            'issued_at' => now()->toIso8601String(),
            'seats' => [
                'used' => $key->activeActivations()->count(),
                'max' => $key->max_activations,
            ],
        ];

        $signed = $this->signer->sign($payload);

        $this->log($key, $this->eventName($request), $request, $data, true, null);
        $this->anomalyDetector->inspect($key, (string) $request->ip());

        return ['status' => 200, 'signed' => $signed];
    }

    private function refuse(Request $request, ?LicenseKey $key, string $reason, array $data): array
    {
        $payload = [
            'valid' => false,
            'reason' => $reason,
            'nonce' => $data['nonce'] ?? null,
            'machine_fingerprint' => $data['machine_fingerprint'] ?? null,
            'issued_at' => now()->toIso8601String(),
        ];

        $signed = $this->signer->sign($payload);

        $this->log($key, $this->eventName($request), $request, $data, false, $reason);

        return ['status' => 200, 'signed' => $signed];
    }

    private function eventName(Request $request): string
    {
        return trim($request->path(), '/') !== '' ? last(explode('/', $request->path())) : 'unknown';
    }

    private function log(?LicenseKey $key, string $event, Request $request, array $data, bool $success, ?string $reason): void
    {
        // Le clair de la clé n'est jamais journalisé (§7.3.2) : license_key_id
        // suffit à retrouver la clé, et key_last4 permet une lecture rapide
        // sans exposer sa valeur complète.
        LicenseLog::query()->create([
            'license_key_id' => $key?->id,
            'event' => $event,
            'machine_fingerprint' => $data['machine_fingerprint'] ?? null,
            'ip' => $request->ip(),
            'success' => $success,
            'reason' => $reason,
            'meta' => $key ? ['key_last4' => $key->key_last4] : null,
        ]);
    }
}
