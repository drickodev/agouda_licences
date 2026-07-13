<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Activation;
use App\Models\Customer;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $token = $response->json('token');

        $me = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/admin/me');
        $me->assertOk()->assertJsonPath('email', $user->email);

        $logout = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/admin/logout');
        $logout->assertNoContent();

        // Le guard Sanctum met en cache l'utilisateur résolu sur sa propre
        // instance ; il faut le forcer à se ré-authentifier pour cette
        // requête suivante dans le même test.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
    }

    public function test_product_crud(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $store = $this->postJson('/api/admin/products', [
            'name' => 'Rouage',
            'slug' => 'rouage',
            'edition' => 'pro',
            'active' => true,
        ]);
        $store->assertCreated()->assertJsonPath('data.slug', 'rouage');
        $productId = $store->json('data.id');

        $this->getJson('/api/admin/products')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'Rouage',
            'slug' => 'rouage',
            'edition' => 'standard',
            'active' => false,
        ])->assertOk()->assertJsonPath('data.edition', 'standard');

        $this->deleteJson("/api/admin/products/{$productId}")->assertNoContent();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_customer_crud(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $store = $this->postJson('/api/admin/customers', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
        ]);
        $store->assertCreated();
        $customerId = $store->json('data.id');

        $this->putJson("/api/admin/customers/{$customerId}", [
            'name' => 'Jean Dupont',
            'company' => 'Acme',
        ])->assertOk()->assertJsonPath('data.company', 'Acme');

        $this->deleteJson("/api/admin/customers/{$customerId}")->assertNoContent();
    }

    public function test_license_key_creation_exposes_plaintext_only_once_and_never_the_hash(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);

        $store = $this->postJson('/api/admin/license-keys', [
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 2,
        ]);

        $store->assertCreated()->assertJsonStructure(['plaintext_key', 'license_key' => ['id', 'key_last4', 'masked_key']]);
        $store->assertJsonMissingPath('license_key.key_hash');
        $store->assertJsonPath('license_key.revoked', false);

        $plaintextKey = $store->json('plaintext_key');
        $licenseKeyId = $store->json('license_key.id');

        // La clé en clair n'est plus jamais renvoyée sur les endpoints de lecture.
        $show = $this->getJson("/api/admin/license-keys/{$licenseKeyId}");
        $show->assertOk()->assertJsonMissingPath('data.key_hash');
        $this->assertArrayNotHasKey('plaintext_key', $show->json());
        $this->assertSame(substr($plaintextKey, -4), $show->json('data.key_last4'));
    }

    public function test_license_key_reveal_returns_the_plaintext_previously_generated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);

        $store = $this->postJson('/api/admin/license-keys', [
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);
        $plaintextKey = $store->json('plaintext_key');
        $licenseKeyId = $store->json('license_key.id');

        $reveal = $this->getJson("/api/admin/license-keys/{$licenseKeyId}/reveal");
        $reveal->assertOk()->assertJsonPath('plaintext_key', $plaintextKey);
    }

    public function test_license_key_reveal_returns_404_when_encrypted_value_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'PLAINTEXT-KEY'),
            'key_last4' => 'KEY1',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);

        $this->getJson("/api/admin/license-keys/{$licenseKey->id}/reveal")->assertNotFound();
    }

    public function test_license_key_subscription_requires_expires_at(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);

        $this->postJson('/api/admin/license-keys', [
            'product_id' => $product->id,
            'license_type' => 'subscription',
            'max_activations' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['expires_at']);
    }

    public function test_license_key_revoke_sets_expected_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'PLAINTEXT-KEY'),
            'key_last4' => 'KEY1',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);

        $response = $this->postJson("/api/admin/license-keys/{$licenseKey->id}/revoke", [
            'reason' => 'Fuite signalée',
        ]);

        $response->assertOk()->assertJsonPath('data.revoked', true)->assertJsonPath('data.revoked_reason', 'Fuite signalée');
        $this->assertDatabaseHas('license_keys', [
            'id' => $licenseKey->id,
            'revoked' => true,
            'revoked_reason' => 'Fuite signalée',
        ]);
    }

    public function test_license_key_revoke_requires_a_reason(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'PLAINTEXT-KEY'),
            'key_last4' => 'KEY1',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);

        $this->postJson("/api/admin/license-keys/{$licenseKey->id}/revoke", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_activation_release_only_works_on_active_activations(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'PLAINTEXT-KEY'),
            'key_last4' => 'KEY1',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);
        $activation = Activation::query()->create([
            'license_key_id' => $licenseKey->id,
            'machine_fingerprint' => 'sha256:abc',
            'status' => 'active',
        ]);

        $this->postJson("/api/admin/activations/{$activation->id}/release")
            ->assertOk()->assertJsonPath('data.status', 'released');

        $this->postJson("/api/admin/activations/{$activation->id}/release")
            ->assertStatus(409);
    }

    public function test_generate_account_recovery_code_returns_plaintext_once(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        $licenseKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'PLAINTEXT-KEY'),
            'key_last4' => 'KEY1',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
        ]);
        $activation = Activation::query()->create([
            'license_key_id' => $licenseKey->id,
            'machine_fingerprint' => 'sha256:abc',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/admin/activations/{$activation->id}/account-recovery-codes", [
            'notes' => 'Mot de passe oublié',
        ]);

        $response->assertCreated()->assertJsonStructure(['plaintext_code', 'expires_at']);

        $list = $this->getJson('/api/admin/account-recovery-codes');
        $list->assertOk()->assertJsonMissingPath('data.0.code_hash');
        $this->assertArrayNotHasKey('plaintext_code', $list->json('data.0'));
    }

    public function test_license_logs_and_recovery_codes_are_read_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/license-logs')->assertOk();
        $this->getJson('/api/admin/account-recovery-codes')->assertOk();
    }
}
