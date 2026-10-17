<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ScheduledCommandFailureReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_scheduled_command_records_a_failure(): void
    {
        Log::spy();
        $events = $this->scheduledEvents();

        foreach ($events as $event) {
            $event->exitCode = 1;
            $event->callAfterCallbacks($this->app);
        }

        $failureLogs = SystemLog::query()->where('event_type', 'scheduler.command.failed')->get();
        $this->assertCount(count($events), $failureLogs);
        $this->assertSame('system', $failureLogs->first()->category);
        $this->assertTrue(
            $failureLogs->contains(fn (SystemLog $log) => $log->description === 'Scheduled command [hoshiask:backup] failed.')
        );
        Log::shouldHaveReceived('error')->times(count($events));
    }

    public function test_successful_scheduled_commands_record_nothing(): void
    {
        Log::spy();

        foreach ($this->scheduledEvents() as $event) {
            $event->exitCode = 0;
            $event->callAfterCallbacks($this->app);
        }

        $this->assertSame(0, SystemLog::query()->where('event_type', 'scheduler.command.failed')->count());
        Log::shouldNotHaveReceived('error');
    }

    public function test_a_killed_alert_email_run_does_not_block_the_next_ones_for_a_day(): void
    {
        $alertEvent = collect($this->scheduledEvents())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'tickets:send-alert-emails'));

        $this->assertNotNull($alertEvent);
        $this->assertTrue($alertEvent->withoutOverlapping);
        $this->assertSame(10, $alertEvent->expiresAt);
    }

    /**
     * @return array<int, Event>
     */
    private function scheduledEvents(): array
    {
        $events = $this->app->make(Schedule::class)->events();
        $this->assertNotEmpty($events);

        return $events;
    }
}
