<?php

namespace Tests\Unit;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketSlaPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 2, 25, 12, 0, 0));
        config([
            'hoshiask.sla.resolution_target_minutes' => 240,
            'hoshiask.sla.assignment_reminder_minutes' => 210,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_open_ticket_sla_badge_shows_on_track_before_risk_window(): void
    {
        $ticket = $this->ticket([
            'status' => 'open',
            'created_at' => now()->subMinutes(45),
        ]);

        $this->assertSame('On track - 45m', $ticket->sla_badge_label);
        $this->assertStringContainsString('text-sky-700', $ticket->sla_badge_class);
    }

    public function test_open_ticket_sla_badge_shows_risk_and_breached_states(): void
    {
        $riskTicket = $this->ticket([
            'status' => 'in_progress',
            'created_at' => now()->subMinutes(211),
        ]);
        $breachedTicket = $this->ticket([
            'status' => 'open',
            'created_at' => now()->subMinutes(241),
        ]);

        $this->assertSame('SLA risk - 3h 31m', $riskTicket->sla_badge_label);
        $this->assertStringContainsString('text-orange-700', $riskTicket->sla_badge_class);
        $this->assertSame('Breached - 4h 1m', $breachedTicket->sla_badge_label);
        $this->assertStringContainsString('text-red-700', $breachedTicket->sla_badge_class);
    }

    public function test_pending_and_completed_ticket_sla_badges_are_contextual(): void
    {
        $pendingTicket = $this->ticket([
            'status' => 'pending',
            'created_at' => now()->subMinutes(30),
        ]);
        $completedTicket = $this->ticket([
            'status' => 'closed',
            'created_at' => now()->subMinutes(90),
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        $this->assertSame('Awaiting client - 30m', $pendingTicket->sla_badge_label);
        $this->assertStringContainsString('text-amber-700', $pendingTicket->sla_badge_class);
        $this->assertSame('Completed - 1h 30m', $completedTicket->sla_badge_label);
        $this->assertStringContainsString('text-emerald-700', $completedTicket->sla_badge_class);
    }

    public function test_sla_badge_uses_configured_thresholds(): void
    {
        config([
            'hoshiask.sla.resolution_target_minutes' => 60,
            'hoshiask.sla.assignment_reminder_minutes' => 30,
        ]);

        $ticket = $this->ticket([
            'status' => 'in_progress',
            'created_at' => now()->subMinutes(31),
        ]);

        $this->assertSame('SLA risk - 31m', $ticket->sla_badge_label);
    }

    private function ticket(array $attributes): Ticket
    {
        $ticket = new Ticket;
        $ticket->forceFill($attributes);

        return $ticket;
    }
}
