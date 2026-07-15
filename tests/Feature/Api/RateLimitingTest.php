<?php

namespace Tests\Feature\Api;

use App\Models\LicenseLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §audit sécurité, point 2 : X-App-Token est un secret unique partagé par
 * tous les clients distribués. Défense en profondeur : throttle par IP et
 * par empreinte machine sur les 4 routes /v1 sensibles, et détection
 * d'abus (trop d'empreintes machine distinctes en peu de temps).
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function appToken(): string
    {
        return (string) config('license.app_token');
    }

    private function fingerprint(): string
    {
        return 'sha256:'.str_repeat('a', 64);
    }

    public function test_validate_endpoint_is_throttled_after_ten_requests_per_minute(): void
    {
        $payload = [
            'key' => 'DOES-NOT-EXIST',
            'machine_fingerprint' => $this->fingerprint(),
            'nonce' => 'nonceeeeeeee',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/validate', $payload, ['X-App-Token' => $this->appToken()])
                ->assertStatus(200);
        }

        $eleventh = $this->postJson('/api/v1/validate', $payload, ['X-App-Token' => $this->appToken()]);
        $eleventh->assertStatus(429);

        $this->assertDatabaseHas('license_logs', [
            'event' => 'rate_limited',
        ]);
    }

    public function test_account_recovery_redeem_is_throttled_after_five_requests_per_minute(): void
    {
        $payload = [
            'code' => 'DOESNOTEX',
            'machine_fingerprint' => $this->fingerprint(),
            'nonce' => 'nonceeeeeeee',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/account-recovery/redeem', $payload, ['X-App-Token' => $this->appToken()])
                ->assertStatus(200);
        }

        $this->postJson('/api/v1/account-recovery/redeem', $payload, ['X-App-Token' => $this->appToken()])
            ->assertStatus(429);
    }

    public function test_throttle_applies_per_machine_fingerprint_even_across_different_ips(): void
    {
        $fingerprint = $this->fingerprint();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/validate', [
                'key' => 'DOES-NOT-EXIST',
                'machine_fingerprint' => $fingerprint,
                'nonce' => 'nonceeeeeeee',
            ], [
                'X-App-Token' => $this->appToken(),
                'REMOTE_ADDR' => '10.0.0.'.($i + 1),
            ])->assertStatus(200);
        }

        $this->postJson('/api/v1/validate', [
            'key' => 'DOES-NOT-EXIST',
            'machine_fingerprint' => $fingerprint,
            'nonce' => 'nonceeeeeeee',
        ], [
            'X-App-Token' => $this->appToken(),
            'REMOTE_ADDR' => '10.0.0.99',
        ])->assertStatus(429);
    }

    public function test_app_token_abuse_is_flagged_when_too_many_distinct_fingerprints_are_seen(): void
    {
        config(['license.token_abuse.distinct_fingerprint_threshold' => 3]);

        foreach (['aaa', 'bbb', 'ccc'] as $suffix) {
            LicenseLog::query()->create([
                'event' => 'validate',
                'machine_fingerprint' => 'sha256:'.str_repeat($suffix, 21).'a',
                'success' => true,
            ]);
        }

        $this->postJson('/api/v1/validate', [
            'key' => 'DOES-NOT-EXIST',
            'machine_fingerprint' => 'sha256:'.str_repeat('d', 64),
            'nonce' => 'nonceeeeeeee',
        ], ['X-App-Token' => $this->appToken()])->assertStatus(200);

        $this->assertDatabaseHas('license_logs', [
            'event' => 'app_token_abuse_suspected',
        ]);
    }
}
