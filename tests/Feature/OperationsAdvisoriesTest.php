<?php

namespace Tests\Feature;

use App\Events\ProductionAdvisoriesRequested;
use App\Http\Controllers\HealthCheckController;
use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Advisories name settings that leave the site up but switch something off (email, log rotation,
 * the scheduler). They are shown, and they must never turn the status into a deploy-failing warning.
 */
class OperationsAdvisoriesTest extends TestCase
{
    use RefreshDatabase;

    private function production(): void
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
        // Only the built-in advisories here; features that add their own (app/Listeners) test them separately.
        Event::fake([ProductionAdvisoriesRequested::class]);
    }

    public function test_a_well_configured_production_site_has_no_advisories(): void
    {
        $this->production();

        $status = (new HelpdeskOperationsStatusService)->buildStatus(false);

        $this->assertSame([], $status['advisories']);
        $this->assertSame([], $status['warnings']);
        $this->assertSame('ok', $status['status']);
    }

    public function test_each_silent_misconfiguration_is_named_and_none_of_them_is_a_warning(): void
    {
        $this->production();
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'helpdesk@yourdomain.com',
            'logging.channels.stack.channels' => ['single'],
            'app.timezone' => 'UTC',
        ]);
        Cache::forever(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->subMinutes(40)->timestamp);

        $status = (new HelpdeskOperationsStatusService)->buildStatus(false);

        $text = implode("\n", $status['advisories']);
        $this->assertStringContainsString('MAIL_MAILER is "log"', $text);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS is still the placeholder', $text);
        $this->assertStringContainsString('LOG_STACK=single', $text);
        $this->assertStringContainsString('APP_TIMEZONE is UTC', $text);
        $this->assertStringContainsString('The scheduler last ran 40 minutes ago', $text);
        $this->assertCount(5, $status['advisories']);

        $this->assertSame([], $status['warnings'], 'advisories never fail a deploy');
        $this->assertSame('ok', $status['status']);
    }

    public function test_a_scheduler_that_has_never_reported_is_an_advisory(): void
    {
        $this->production();
        Cache::forget(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY);

        $advisories = (new HelpdeskOperationsStatusService)->buildStatus(false)['advisories'];

        $this->assertCount(1, $advisories);
        $this->assertStringContainsString('has not reported in yet', $advisories[0]);
    }

    public function test_a_recent_heartbeat_is_fine(): void
    {
        $this->production();
        Cache::forever(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->subMinutes(14)->timestamp);

        $this->assertSame([], (new HelpdeskOperationsStatusService)->buildStatus(false)['advisories']);
    }

    public function test_nothing_is_advised_outside_production(): void
    {
        config(['mail.default' => 'log', 'app.timezone' => 'UTC']);
        Cache::forget(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY);

        $this->assertSame([], (new HelpdeskOperationsStatusService)->buildStatus(false)['advisories']);
    }

    public function test_the_five_minute_command_records_the_heartbeat(): void
    {
        Cache::forget(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $this->assertEqualsWithDelta(now()->timestamp, (int) Cache::get(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY), 5);
    }

    public function test_the_ops_status_command_lists_advisories_and_still_succeeds_with_fail_on_warning(): void
    {
        $this->production();
        config(['mail.default' => 'log']);

        $this->artisan('helpdesk:ops-status', ['--fail-on-warning' => true])
            ->expectsOutputToContain('Advisories (the deploy does not stop for these')
            ->expectsOutputToContain('MAIL_MAILER is "log"')
            ->expectsOutput('Warnings: none')
            ->assertSuccessful();
    }

    public function test_the_ops_status_json_and_the_detailed_health_check_carry_the_advisories(): void
    {
        $this->production();
        config(['mail.default' => 'log']);

        $this->artisan('helpdesk:ops-status', ['--json' => true, '--fail-on-warning' => true])
            ->expectsOutputToContain('"advisories"')
            ->assertSuccessful();

        // Called directly: a request to the production host would leave Symfony's static
        // trusted-host list set for every test that runs after this one.
        $health = fn (string $token): array => app(HealthCheckController::class)(
            Request::create('/health', 'GET', [], [], [], ['HTTP_X_HEALTH_TOKEN' => $token]),
            app(HelpdeskOperationsStatusService::class)
        )->getData(true);

        $detailed = $health('monitor-secret');
        $this->assertSame('ok', $detailed['status']);
        $this->assertStringContainsString('MAIL_MAILER is "log"', $detailed['advisories'][0]);
        $this->assertArrayNotHasKey('advisories', $health('wrong-token'));
    }
}
