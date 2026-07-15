<?php

namespace Tests\Feature\Api\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §audit sécurité, point 1 : /api/documentation et /docs/api-docs.json ne
 * doivent jamais être accessibles sans authentification.
 */
class SwaggerDocsProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_are_disabled_when_no_credentials_are_configured(): void
    {
        config(['license.docs.username' => null, 'license.docs.password' => null]);

        $this->get('/api/documentation')->assertNotFound();
    }

    public function test_docs_require_authentication_when_credentials_are_configured(): void
    {
        config(['license.docs.username' => 'docs-admin', 'license.docs.password' => 'secret']);

        $this->get('/api/documentation')->assertUnauthorized();
    }

    public function test_docs_reject_wrong_basic_auth_credentials(): void
    {
        config(['license.docs.username' => 'docs-admin', 'license.docs.password' => 'secret']);

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('docs-admin:wrong-password'),
        ])->get('/api/documentation')->assertUnauthorized();
    }

    public function test_docs_are_accessible_with_correct_basic_auth_credentials(): void
    {
        config(['license.docs.username' => 'docs-admin', 'license.docs.password' => 'secret']);

        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('docs-admin:secret'),
        ])->get('/api/documentation');

        $response->assertOk();
    }

    public function test_docs_are_rejected_from_a_non_whitelisted_ip_even_with_correct_credentials(): void
    {
        config([
            'license.docs.username' => 'docs-admin',
            'license.docs.password' => 'secret',
            'license.admin_allowed_ips' => ['203.0.113.10'],
        ]);

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('docs-admin:secret'),
        ])->get('/api/documentation')->assertForbidden();
    }
}
