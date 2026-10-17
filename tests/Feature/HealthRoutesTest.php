<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthRoutesTest extends TestCase
{
    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_health_endpoint_returns_minimal_public_payload(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('hoshiask.health.detail_token', null);

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'checked_at'])
            ->assertJsonMissingPath('queue')
            ->assertJsonMissingPath('warnings');
    }

    public function test_health_endpoint_returns_details_with_valid_token(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('hoshiask.health.detail_token', 'monitor-secret');

        $response = $this->withHeader('X-Health-Token', 'monitor-secret')
            ->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('queue.connection', 'sync')
            ->assertJsonPath('checks.queue', 'ok')
            ->assertJsonPath('warnings', []);
    }

    public function test_health_endpoint_no_longer_accepts_the_token_via_query_string(): void
    {
        config()->set('queue.default', 'sync');
        config()->set('hoshiask.health.detail_token', 'monitor-secret');

        $response = $this->getJson('/health?token=monitor-secret');

        $response->assertOk()
            ->assertJsonMissingPath('queue')
            ->assertJsonMissingPath('warnings');
    }

    public function test_health_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/health')->assertOk();
        }

        $this->getJson('/health')->assertStatus(429);
    }
}
