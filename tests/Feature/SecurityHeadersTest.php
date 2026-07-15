<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * §audit sécurité, points 5 et 6 : CORS restreint et en-têtes de sécurité
 * HTTP présents sur toutes les réponses de l'API.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_every_response(): void
    {
        $response = $this->getJson('/api/admin/products');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_header_is_set_on_secure_requests(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->withServerVariables(['HTTPS' => 'on'])->getJson('/api/admin/products');

        $response->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
    }

    public function test_cors_does_not_allow_a_wildcard_origin_on_admin_login(): void
    {
        config(['cors.allowed_origins' => []]);

        $response = $this->withHeaders([
            'Origin' => 'https://evil.example.com',
        ])->postJson('/api/admin/login', ['email' => 'a@a.com', 'password' => 'wrong']);

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_cors_allows_a_configured_origin(): void
    {
        config(['cors.allowed_origins' => ['https://admin.agoudatech.com']]);

        $response = $this->withHeaders([
            'Origin' => 'https://admin.agoudatech.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/admin/login');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://admin.agoudatech.com');
    }
}
