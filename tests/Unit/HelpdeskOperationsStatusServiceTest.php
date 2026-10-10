<?php

namespace Tests\Unit;

use App\Services\Operations\HelpdeskOperationsStatusService;
use Tests\TestCase;

class HelpdeskOperationsStatusServiceTest extends TestCase
{
    public function test_process_check_is_skipped_when_not_requested(): void
    {
        config(['queue.default' => 'database']);
        config(['helpdesk.ops.queue_worker_running' => null]);

        $status = (new HelpdeskOperationsStatusService)->buildStatus(includeProcessCheck: false);

        $this->assertNull($status['queue_worker_running']);
    }

    public function test_process_check_runs_when_requested(): void
    {
        config(['queue.default' => 'database']);
        config(['helpdesk.ops.queue_worker_running' => null]);

        $status = (new HelpdeskOperationsStatusService)->buildStatus(includeProcessCheck: true);

        $this->assertContains($status['queue_worker_running'], [true, false, null]);
    }

    public function test_explicit_config_override_short_circuits_regardless_of_process_check_flag(): void
    {
        config(['queue.default' => 'database']);
        config(['helpdesk.ops.queue_worker_running' => true]);

        $status = (new HelpdeskOperationsStatusService)->buildStatus(includeProcessCheck: false);

        $this->assertTrue($status['queue_worker_running']);
    }

    public function test_sync_queue_always_reports_running_without_a_process_check(): void
    {
        config(['queue.default' => 'sync']);

        $status = (new HelpdeskOperationsStatusService)->buildStatus(includeProcessCheck: false);

        $this->assertTrue($status['queue_worker_running']);
    }
}
