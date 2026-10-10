<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HelpdeskOpsStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_status_reports_queue_requirements_and_job_counts(): void
    {
        config()->set('queue.default', 'database');
        config()->set('mail.default', 'smtp');
        config()->set('helpdesk.ops.queue_worker_running', false);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"DemoJob"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{"displayName":"FailedDemoJob"}',
            'exception' => 'Demo failure',
            'failed_at' => now(),
        ]);

        $this->artisan('helpdesk:ops-status')
            ->expectsOutput('Helpdesk operations status')
            ->expectsOutput('Queue connection: database')
            ->expectsOutput('Queue worker required: yes')
            ->expectsOutput('Queue worker status: not detected')
            ->expectsOutput('Pending jobs: 1')
            ->expectsOutput('Failed jobs: 1')
            ->expectsOutput('Warnings:')
            ->expectsOutput('- Queue connection "database" requires a running queue worker for queued mail and jobs.')
            ->expectsOutput('- There are 1 pending jobs in the queue.')
            ->expectsOutput('- There are 1 failed jobs that need attention.')
            ->assertSuccessful();
    }

    public function test_ops_status_reports_running_queue_worker_without_warning(): void
    {
        config()->set('queue.default', 'database');
        config()->set('helpdesk.ops.queue_worker_running', true);

        $this->artisan('helpdesk:ops-status')
            ->expectsOutput('Helpdesk operations status')
            ->expectsOutput('Queue connection: database')
            ->expectsOutput('Queue worker required: yes')
            ->expectsOutput('Queue worker status: running')
            ->expectsOutput('Pending jobs: 0')
            ->expectsOutput('Failed jobs: 0')
            ->expectsOutput('Warnings: none')
            ->assertSuccessful();
    }

    public function test_ops_status_can_fail_on_warnings(): void
    {
        config()->set('queue.default', 'database');
        config()->set('helpdesk.ops.queue_worker_running', false);

        $this->artisan('helpdesk:ops-status --fail-on-warning')
            ->expectsOutput('Helpdesk operations status')
            ->expectsOutput('Warnings:')
            ->expectsOutput('- Queue connection "database" requires a running queue worker for queued mail and jobs.')
            ->assertFailed();
    }

    public function test_ops_status_can_output_json(): void
    {
        config()->set('queue.default', 'database');
        config()->set('helpdesk.ops.queue_worker_running', true);

        Artisan::call('helpdesk:ops-status', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('ok', $payload['status']);
        $this->assertSame('database', $payload['queue_connection']);
        $this->assertTrue($payload['database_reachable']);
        $this->assertSame([], $payload['warnings']);
    }
}
