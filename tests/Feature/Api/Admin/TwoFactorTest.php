<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function google2fa(): Google2FA
    {
        return app(Google2FA::class);
    }

    public function test_setup_then_enable_activates_two_factor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $setup = $this->postJson('/api/admin/2fa/setup');
        $setup->assertOk()->assertJsonStructure(['secret', 'otpauth_url']);

        $secret = $setup->json('secret');
        $code = $this->google2fa()->getCurrentOtp($secret);

        $this->getJson('/api/admin/2fa')->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('pending', true);

        $enable = $this->postJson('/api/admin/2fa/enable', ['code' => $code]);
        $enable->assertOk()->assertJsonPath('enabled', true);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_enable_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/2fa/setup');

        $this->postJson('/api/admin/2fa/enable', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_login_with_two_factor_enabled_requires_challenge(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $secret = $this->google2fa()->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $login->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonStructure(['challenge_token']);
        $this->assertArrayNotHasKey('token', $login->json());

        $challengeToken = $login->json('challenge_token');
        $code = $this->google2fa()->getCurrentOtp($secret);

        $challenge = $this->postJson('/api/admin/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ]);

        $challenge->assertOk()->assertJsonStructure(['token', 'user']);

        // Le challenge est à usage unique : le rejouer échoue.
        $this->postJson('/api/admin/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_challenge_rejects_invalid_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $secret = $this->google2fa()->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $login = $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $this->postJson('/api/admin/2fa/challenge', [
            'challenge_token' => $login->json('challenge_token'),
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_disable_requires_a_valid_code_and_clears_the_secret(): void
    {
        $user = User::factory()->create();
        $secret = $this->google2fa()->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($user);

        $code = $this->google2fa()->getCurrentOtp($secret);

        $this->postJson('/api/admin/2fa/disable', ['code' => $code])
            ->assertOk()->assertJsonPath('enabled', false);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
