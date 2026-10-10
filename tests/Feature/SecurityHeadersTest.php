<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_forced_csp_disables_unsafe_eval_for_frontend_runtime(): void
    {
        config()->set('security.csp.force', true);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("script-src-attr 'none'", $csp);
        $this->assertStringContainsString("style-src-attr 'none'", $csp);
    }
}
