<?php

namespace App\Services;

use App\Models\AccountRecoveryCode;
use App\Models\LicenseLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountRecoveryService
{
    public function __construct(private readonly LicenseSigner $signer)
    {
    }

    /**
     * Consomme un code de déblocage de compte local. Ne concerne pas les
     * licences : c'est un canal parallèle qui réutilise la même
     * infrastructure de confiance (signature Ed25519, anti-rejeu) pour
     * autoriser un logiciel client à débloquer un compte local (mot de
     * passe oublié) sans jamais faire confiance à un simple booléen.
     *
     * @return array{status: int, signed: array}
     */
    public function redeem(Request $request, array $data): array
    {
        $code = AccountRecoveryCode::query()->wherePlaintextCode($data['code'])->first();

        if (! $code) {
            return $this->refuse($request, null, 'not_found', $data);
        }

        if ($code->isUsed()) {
            return $this->refuse($request, $code, 'already_used', $data);
        }

        if ($code->isExpired()) {
            return $this->refuse($request, $code, 'expired', $data);
        }

        if ($code->activation->machine_fingerprint !== $data['machine_fingerprint']) {
            return $this->refuse($request, $code, 'machine_mismatch', $data);
        }

        return DB::transaction(function () use ($request, $code, $data) {
            $code->update([
                'used_at' => now(),
                'used_from_ip' => $request->ip(),
            ]);

            $payload = [
                'valid' => true,
                'action' => 'account_recovery',
                'machine_fingerprint' => $data['machine_fingerprint'],
                'nonce' => $data['nonce'],
                'issued_at' => now()->toIso8601String(),
            ];

            $signed = $this->signer->sign($payload);

            $this->log($code, true, null, $request);

            return ['status' => 200, 'signed' => $signed];
        });
    }

    private function refuse(Request $request, ?AccountRecoveryCode $code, string $reason, array $data): array
    {
        $payload = [
            'valid' => false,
            'action' => 'account_recovery',
            'reason' => $reason,
            'nonce' => $data['nonce'] ?? null,
            'machine_fingerprint' => $data['machine_fingerprint'] ?? null,
            'issued_at' => now()->toIso8601String(),
        ];

        $signed = $this->signer->sign($payload);

        $this->log($code, false, $reason, $request);

        return ['status' => 200, 'signed' => $signed];
    }

    private function log(?AccountRecoveryCode $code, bool $success, ?string $reason, Request $request): void
    {
        LicenseLog::query()->create([
            'license_key_id' => $code?->activation?->license_key_id,
            'event' => 'account_recovery_redeemed',
            'machine_fingerprint' => $code?->activation?->machine_fingerprint,
            'ip' => $request->ip(),
            'success' => $success,
            'reason' => $reason,
            'meta' => $code ? ['account_recovery_code_id' => $code->id] : null,
        ]);
    }
}
