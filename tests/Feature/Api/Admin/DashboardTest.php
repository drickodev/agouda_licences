<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Activation;
use App\Models\Customer;
use App\Models\LicenseKey;
use App\Models\LicenseLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_aggregated_stats(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = Product::query()->create(['name' => 'Rouage', 'slug' => 'rouage', 'active' => true]);
        Product::query()->create(['name' => 'Inactif', 'slug' => 'inactif', 'active' => false]);
        Customer::query()->create(['name' => 'Jean Dupont']);

        $activeKey = LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'KEY-ACTIVE'),
            'key_last4' => 'CTIV',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 2,
            'revoked' => false,
        ]);
        LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'KEY-REVOKED'),
            'key_last4' => 'OKED',
            'product_id' => $product->id,
            'license_type' => 'perpetual',
            'max_activations' => 1,
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_reason' => 'test',
        ]);
        LicenseKey::query()->create([
            'key_hash' => hash('sha256', 'KEY-EXPIRING'),
            'key_last4' => 'PIRE',
            'product_id' => $product->id,
            'license_type' => 'subscription',
            'expires_at' => now()->addDays(10),
            'max_activations' => 1,
            'revoked' => false,
        ]);

        Activation::query()->create([
            'license_key_id' => $activeKey->id,
            'machine_fingerprint' => 'sha256:abc',
            'status' => 'active',
        ]);

        LicenseLog::query()->create([
            'license_key_id' => $activeKey->id,
            'event' => 'activate',
            'success' => true,
        ]);
        LicenseLog::query()->create([
            'license_key_id' => $activeKey->id,
            'event' => 'anomaly_detected',
            'success' => false,
        ]);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('products.total', 2)
            ->assertJsonPath('products.active', 1)
            ->assertJsonPath('customers.total', 1)
            ->assertJsonPath('license_keys.total', 3)
            ->assertJsonPath('license_keys.active', 2)
            ->assertJsonPath('license_keys.revoked', 1)
            ->assertJsonPath('license_keys.expiring_soon', 1)
            ->assertJsonPath('activations.active', 1)
            ->assertJsonPath('anomalies_last_7_days', 1)
            ->assertJsonCount(2, 'recent_logs');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }
}
