<?php

namespace Tests\Feature;

use App\Listeners\RecordQueueWorkerHeartbeat;
use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QueueWorkerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 9, 0, 0));
        RecordQueueWorkerHeartbeat::forgetLastWrite();
        Cache::forget(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RecordQueueWorkerHeartbeat::forgetLastWrite();
        parent::tearDown();
    }

    public function test_the_listener_is_registered_for_finished_and_failed_jobs(): void
    {
        $this->assertTrue(Event::hasListeners(JobProcessed::class));
        $this->assertTrue(Event::hasListeners(JobFailed::class));
    }

    public function test_a_finished_or_failed_job_records_the_time_at_most_every_thirty_seconds(): void
    {
        $listener = new RecordQueueWorkerHeartbeat;
        $job = Mockery::mock(Job::class);

        $listener->handle(new JobProcessed('redis', $job));
        $this->assertSame(now()->timestamp, Cache::get(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY));

        Carbon::setTestNow(now()->addSeconds(10));
        $listener->handle(new JobFailed('redis', $job, new RuntimeException('boom')));
        $this->assertSame(now()->subSeconds(10)->timestamp, Cache::get(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY));

        Carbon::setTestNow(now()->addSeconds(25));
        $listener->handle(new JobProcessed('redis', $job));
        $this->assertSame(now()->timestamp, Cache::get(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY));
    }

    public function test_a_redis_queue_with_waiting_jobs_and_no_finished_job_for_a_while_is_an_advisory(): void
    {
        $this->useRedisLikeQueue();
        Cache::forever(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY, now()->subMinutes(20)->timestamp);
        Queue::push('App\\Jobs\\Demo');
        Queue::push('App\\Jobs\\Demo');

        $status = app(HelpdeskOperationsStatusService::class)->buildStatus(false);

        $this->assertSame(2, $status['pending_jobs']);
        $this->assertStringContainsString('2 queued jobs are waiting and no job has finished for 20 minutes', implode("\n", $status['advisories']));
        $this->assertStringNotContainsString('queued jobs are waiting', implode("\n", $status['warnings']));
    }

    public function test_a_recent_heartbeat_or_no_heartbeat_yet_says_nothing(): void
    {
        $this->useRedisLikeQueue();
        Queue::push('App\\Jobs\\Demo');

        $service = app(HelpdeskOperationsStatusService::class);

        // Nothing has finished since the queue was switched: that proves nothing.
        $this->assertStringNotContainsString('no job has finished', implode("\n", $service->buildStatus(false)['advisories']));

        Cache::forever(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY, now()->subMinutes(2)->timestamp);
        $this->assertStringNotContainsString('no job has finished', implode("\n", $service->buildStatus(false)['advisories']));
    }

    public function test_the_status_shows_when_the_last_job_finished(): void
    {
        $this->useRedisLikeQueue();
        Cache::forever(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY, now()->subMinutes(3)->timestamp);

        $status = app(HelpdeskOperationsStatusService::class)->buildStatus(false);

        $this->assertSame(now()->subMinutes(3)->toIso8601String(), $status['queue_last_job_finished_at']);
    }

    private function useRedisLikeQueue(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default']);
        config()->set('helpdesk.ops.queue_worker_running', true);
        Queue::fake();
    }
}
