<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskHealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_status(): void
    {
        config()->set('helpdesk.health.detail_token', 'monitor-secret');

        $response = $this->withHeader('X-Health-Token', 'monitor-secret')
            ->getJson(route('health'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'php' => 'ok',
                'queue' => 'ok',
            ],
        ]);
        $response->assertJsonStructure([
            'status',
            'checked_at',
            'checks' => ['database', 'php', 'queue'],
            'queue' => ['connection', 'worker_required', 'worker_running', 'pending_jobs', 'failed_jobs'],
            'warnings',
        ]);
    }

    public function test_health_endpoint_reports_degraded_status_for_queue_warning(): void
    {
        config()->set('queue.default', 'database');
        config()->set('helpdesk.ops.queue_worker_running', false);
        config()->set('helpdesk.health.detail_token', 'monitor-secret');

        $response = $this->withHeader('X-Health-Token', 'monitor-secret')
            ->getJson(route('health'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'degraded',
            'checks' => [
                'database' => 'ok',
                'php' => 'ok',
                'queue' => 'degraded',
            ],
        ]);
        $response->assertJsonFragment([
            'connection' => 'database',
            'worker_required' => true,
            'worker_running' => false,
        ]);
        $response->assertSee('requires a running queue worker');
    }
}
