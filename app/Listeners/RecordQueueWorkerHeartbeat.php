<?php

namespace App\Listeners;

use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers when a queue worker last finished a job (done or failed), whatever the queue driver. The status check
 * uses it to tell "jobs are waiting and no worker is taking them" on a queue it cannot read directly, such as Redis.
 * Written at most every 30 seconds per worker, and never allowed to fail the job it follows.
 */
class RecordQueueWorkerHeartbeat
{
    private const WRITE_EVERY_SECONDS = 30;

    private static int $lastWrittenAt = 0;

    public function handle(JobProcessed|JobFailed $event): void
    {
        $now = now()->timestamp;
        if ($now - self::$lastWrittenAt < self::WRITE_EVERY_SECONDS) {
            return;
        }

        self::$lastWrittenAt = $now;

        rescue(static fn () => Cache::forever(HelpdeskOperationsStatusService::QUEUE_HEARTBEAT_CACHE_KEY, $now), null, false);
    }

    /** For tests: a worker process lives for hours, a test process must not remember an earlier test. */
    public static function forgetLastWrite(): void
    {
        self::$lastWrittenAt = 0;
    }
}
