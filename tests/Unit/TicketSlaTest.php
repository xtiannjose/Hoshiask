<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Support\TicketSla;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketSlaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 3, 2, 12, 0, 0));
        config(['helpdesk.sla.resolution_target_minutes' => 240, 'helpdesk.sla.assignment_reminder_minutes' => 210]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sla(array $attributes): TicketSla
    {
        return (new Ticket)->forceFill($attributes)->sla();
    }

    public function test_the_target_is_breached_at_exactly_the_target_and_not_a_minute_before(): void
    {
        $this->assertFalse($this->sla(['status' => 'open', 'created_at' => now()->subMinutes(239)])->hasBreachedResolutionTarget());
        $this->assertTrue($this->sla(['status' => 'open', 'created_at' => now()->subMinutes(240)])->hasBreachedResolutionTarget());
    }

    public function test_the_risk_window_starts_at_the_reminder_time(): void
    {
        $this->assertFalse($this->sla(['status' => 'open', 'created_at' => now()->subMinutes(209)])->isNearingResolutionTarget());
        $this->assertTrue($this->sla(['status' => 'open', 'created_at' => now()->subMinutes(210)])->isNearingResolutionTarget());
    }

    public function test_a_done_ticket_stops_the_clock_at_its_first_completion_date(): void
    {
        $sla = $this->sla([
            'status' => 'closed',
            'created_at' => now()->subHours(10),
            'resolved_at' => now()->subHours(8),
            'closed_at' => now()->subHours(1),
        ]);

        $this->assertSame(120, $sla->resolutionMinutes());
        $this->assertSame(120, $sla->elapsedMinutes());
        $this->assertSame('Completed - 2h', $sla->badgeLabel());
        $this->assertFalse($sla->hasBreachedResolutionTarget(now()->addDays(30)), 'a finished ticket does not age');
    }

    public function test_a_cancelled_ticket_is_labelled_cancelled_whatever_its_age(): void
    {
        $sla = $this->sla(['status' => 'cancelled', 'created_at' => now()->subDays(3), 'cancelled_at' => now()->subDays(2)]);

        $this->assertStringStartsWith('Cancelled - ', $sla->badgeLabel());
        $this->assertSame('border-slate-200 bg-slate-50 text-slate-600', $sla->badgeClass());
    }

    public function test_a_ticket_that_was_resolved_late_shows_as_breached(): void
    {
        $sla = $this->sla(['status' => 'resolved', 'created_at' => now()->subHours(10), 'resolved_at' => now()->subHours(1)]);

        $this->assertSame('Breached - 9h', $sla->badgeLabel());
        $this->assertSame('border-red-200 bg-red-50 text-red-700', $sla->badgeClass());
    }

    public function test_elapsed_time_is_written_as_minutes_then_hours_and_minutes(): void
    {
        $this->assertSame('On track - 45m', $this->sla(['status' => 'open', 'created_at' => now()->subMinutes(45)])->badgeLabel());
        $this->assertSame('On track - 2h', $this->sla(['status' => 'open', 'created_at' => now()->subMinutes(120)])->badgeLabel());
        $this->assertSame('SLA risk - 3h 35m', $this->sla(['status' => 'open', 'created_at' => now()->subMinutes(215)])->badgeLabel());
    }

    public function test_a_ticket_without_a_logged_date_has_no_sla_figures(): void
    {
        $sla = $this->sla(['status' => 'open']);

        $this->assertNull($sla->elapsedMinutes());
        $this->assertNull($sla->resolutionMinutes());
        $this->assertNull($sla->badgeLabel());
        $this->assertFalse($sla->hasBreachedResolutionTarget());
    }

    public function test_the_timestamp_rule_gives_the_same_minutes_as_the_ticket_for_every_combination_of_dates(): void
    {
        $created = now()->subHours(30);
        $cases = [
            'resolved only' => ['resolved_at' => $created->copy()->addMinutes(95)],
            'closed only' => ['closed_at' => $created->copy()->addMinutes(95)],
            'cancelled only' => ['cancelled_at' => $created->copy()->addMinutes(95)],
            'resolved and closed' => ['resolved_at' => $created->copy()->addMinutes(95), 'closed_at' => $created->copy()->addHours(20)],
            'closed and cancelled' => ['closed_at' => $created->copy()->addMinutes(95), 'cancelled_at' => $created->copy()->addHours(20)],
            'a whole minute and 59 seconds' => ['resolved_at' => $created->copy()->addSeconds(119)],
            'before it was logged' => ['resolved_at' => $created->copy()->subMinutes(10)],
            'nothing yet' => [],
        ];

        foreach ($cases as $name => $dates) {
            $fromTicket = $this->sla(['status' => 'closed', 'created_at' => $created, ...$dates])->resolutionMinutes();
            $stamp = static fn (string $column): ?int => isset($dates[$column]) ? $dates[$column]->getTimestamp() : null;
            $fromStamps = TicketSla::minutesToCompletion($created->getTimestamp(), $stamp('resolved_at'), $stamp('closed_at'), $stamp('cancelled_at'));

            $this->assertSame($fromTicket, $fromStamps, $name);
        }
    }
}
