<?php

namespace Tests\Feature;

use App\Events\ProductionAdvisoriesRequested;
use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdvisoryExtensionPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_listener_can_add_an_advisory_to_the_production_status_without_creating_a_warning(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://helpdesk.example.org',
            'session.secure' => true,
            'helpdesk.health.detail_token' => 'monitor-secret',
            'queue.default' => 'sync',
            'mail.default' => 'smtp',
            'mail.from.address' => 'helpdesk@example.org',
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['daily'],
            'app.timezone' => 'Asia/Manila',
        ]);
        Cache::forever(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->timestamp);
        Event::listen(ProductionAdvisoriesRequested::class, fn (ProductionAdvisoriesRequested $request) => $request->add('A feature has something to say.'));

        $status = (new HelpdeskOperationsStatusService)->buildStatus(false);

        $this->assertContains('A feature has something to say.', $status['advisories']);
        $this->assertSame([], $status['warnings']);
        $this->assertSame('ok', $status['status']);
    }

    public function test_listeners_are_not_asked_outside_production(): void
    {
        Event::listen(ProductionAdvisoriesRequested::class, fn (ProductionAdvisoriesRequested $request) => $request->add('Should not appear.'));

        $status = (new HelpdeskOperationsStatusService)->buildStatus(false);

        $this->assertSame([], $status['advisories']);
    }
}
