<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequireTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_keys_are_blocked_without_two_factor_enabled(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/license-keys')->assertForbidden();
    }

    public function test_customers_are_blocked_without_two_factor_enabled(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/customers')->assertForbidden();
    }

    public function test_license_keys_are_reachable_once_two_factor_is_enabled(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/license-keys')->assertOk();
    }

    public function test_routes_not_listed_as_sensitive_remain_reachable_without_two_factor(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/products')->assertOk();
        $this->getJson('/api/admin/dashboard')->assertOk();
    }
}
