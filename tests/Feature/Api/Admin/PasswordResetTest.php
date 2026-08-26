<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use App\Notifications\AdminPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_sends_a_reset_code_when_the_account_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/admin/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, AdminPasswordResetNotification::class);
    }

    public function test_forgot_returns_the_same_generic_message_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/admin/password/forgot', ['email' => 'nobody@example.com']);

        $response->assertOk()->assertJsonStructure(['message']);
        Notification::assertNothingSent();
    }

    public function test_reset_updates_the_password_and_revokes_existing_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = $user->createToken('flutter-admin')->plainTextToken;
        $this->assertNotEmpty($user->tokens()->count());

        $this->postJson('/api/admin/password/forgot', ['email' => $user->email])->assertOk();

        $plainToken = null;
        Notification::assertSentTo($user, AdminPasswordResetNotification::class, function ($notification) use (&$plainToken) {
            $plainToken = (fn () => $this->token)->call($notification);

            return true;
        });

        $this->postJson('/api/admin/password/reset', [
            'email' => $user->email,
            'token' => $plainToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->postJson('/api/admin/password/reset', [
            'email' => $user->email,
            'token' => 'not-the-right-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_forgot_endpoint_is_throttled_after_six_requests_per_minute(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/admin/password/forgot', ['email' => $user->email])->assertOk();
        }

        $this->postJson('/api/admin/password/forgot', ['email' => $user->email])->assertStatus(429);
    }
}
