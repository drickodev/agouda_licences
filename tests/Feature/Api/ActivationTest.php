<?php

namespace Tests\Feature\Api;

use App\Models\LicenseKey;
use App\Models\Product;
use App\Services\LicenseKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Régression : un client qui désactive sa licence (transfert de machine) puis la
 * réactive sur la MÊME machine (retour, ou simple re-saisie de la clé) provoquait
 * un HTTP 500 — la contrainte unique (license_key_id, machine_fingerprint) sur
 * `activations` n'est pas scoped par statut, et activate() ne cherchait qu'une
 * activation "active" avant de retomber sur un create() qui percutait la ligne
 * "released" laissée par deactivate(). Trouvé en intégrant AVIGEST (drickoweb).
 */
class ActivationTest extends TestCase
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

    private function createLicenseKey(int $maxActivations = 1): array
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'active' => true,
        ]);

        $plaintextKey = 'AAAAA-BBBBB-CCCCC-DDDDD';

        $key = LicenseKey::query()->create([
            'key_hash' => LicenseKeyGenerator::hash($plaintextKey),
            'key_last4' => substr($plaintextKey, -4),
            'key_encrypted' => $plaintextKey,
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => $maxActivations,
            'revoked' => false,
        ]);

        return [$plaintextKey, $product, $key];
    }

    public function test_reactivating_the_same_machine_after_deactivate_succeeds_instead_of_500(): void
    {
        [$plaintextKey, $product] = $this->createLicenseKey();
        $fingerprint = $this->fingerprint();
        $headers = ['X-App-Token' => $this->appToken()];

        $activate = fn () => $this->postJson('/api/v1/activate', [
            'key' => $plaintextKey,
            'product_slug' => $product->slug,
            'machine_fingerprint' => $fingerprint,
            'nonce' => 'nonce-activate-1',
        ], $headers);

        $activate()->assertStatus(200);

        $this->postJson('/api/v1/deactivate', [
            'key' => $plaintextKey,
            'machine_fingerprint' => $fingerprint,
            'nonce' => 'nonce-deactivate-1',
        ], $headers)->assertStatus(200);

        // Avant correctif : 500 (QueryException non interceptée sur la contrainte unique).
        $reactivation = $activate();
        $reactivation->assertStatus(200);
        $reactivation->assertJsonPath('payload', fn (string $payload) => json_decode($payload, true)['valid'] === true);

        $this->assertDatabaseHas('activations', [
            'license_key_id' => $product->licenseKeys()->first()->id,
            'machine_fingerprint' => $fingerprint,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('activations', 1); // réutilisée, pas dupliquée
    }

    public function test_reactivating_does_not_exceed_the_seat_limit(): void
    {
        [$plaintextKey, $product] = $this->createLicenseKey(maxActivations: 1);
        $fingerprintA = $this->fingerprint();
        $fingerprintB = 'sha256:'.str_repeat('b', 64);
        $headers = ['X-App-Token' => $this->appToken()];

        $this->postJson('/api/v1/activate', [
            'key' => $plaintextKey, 'product_slug' => $product->slug,
            'machine_fingerprint' => $fingerprintA, 'nonce' => 'nonce-activate-a',
        ], $headers)->assertStatus(200);

        $this->postJson('/api/v1/deactivate', [
            'key' => $plaintextKey, 'machine_fingerprint' => $fingerprintA, 'nonce' => 'nonce-deactivate-b',
        ], $headers)->assertStatus(200);

        // Le siège libéré est repris par une autre machine.
        $this->postJson('/api/v1/activate', [
            'key' => $plaintextKey, 'product_slug' => $product->slug,
            'machine_fingerprint' => $fingerprintB, 'nonce' => 'nonce-activate-c',
        ], $headers)->assertStatus(200);

        // La première machine ne doit plus pouvoir reprendre le siège désormais occupé.
        $response = $this->postJson('/api/v1/activate', [
            'key' => $plaintextKey, 'product_slug' => $product->slug,
            'machine_fingerprint' => $fingerprintA, 'nonce' => 'nonce-activate-d',
        ], $headers);
        $response->assertStatus(200);
        $payload = json_decode($response->json('payload'), true);
        $this->assertFalse($payload['valid']);
        $this->assertSame('seat_limit_reached', $payload['reason']);
    }
}
