<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A Pending ticket waits on the client. Its row badge says "Awaiting client" and never "Breached", so the lists, the
 * dashboard count and the reminder emails leave it out of the SLA too.
 */
class PendingTicketsSlaTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $staff;

    private Ticket $working;

    private Ticket $pending;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
        $this->staff = $this->createSupportUser();
        $technician = $this->createAssignedSupportUser();
        $client = $this->createClient();
        $category = $this->createCategory();

        foreach (['working' => 'in_progress', 'pending' => 'pending'] as $property => $status) {
            $ticket = $this->createTicket($client, $category, [
                'subject' => ucfirst($property).' ticket',
                'status' => $status,
                'assigned_to' => $technician->id,
                'assigned_at' => now()->subDays(2),
            ]);
            $ticket->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->saveQuietly();
            $ticket->assignedUsers()->syncWithoutDetaching([$technician->id]);
            $this->{$property} = $ticket;
        }
    }

    public function test_the_sla_breached_tab_leaves_out_a_pending_ticket(): void
    {
        $html = $this->actingAs($this->staff)
            ->getJson(route('admin.tickets.index', ['tab' => 'breached', 'partial' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString($this->working->ticket_number, $html);
        $this->assertStringNotContainsString($this->pending->ticket_number, $html);
    }

    public function test_the_needs_attention_tab_and_count_leave_out_a_pending_ticket(): void
    {
        $html = $this->actingAs($this->staff)
            ->getJson(route('admin.tickets.index', ['tab' => 'attention', 'partial' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString($this->working->ticket_number, $html);
        $this->assertStringNotContainsString($this->pending->ticket_number, $html);

        $this->actingAs($this->staff)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['attention_tickets'] === 1);
    }

    public function test_a_pending_ticket_gets_no_sla_reminder_email(): void
    {
        Mail::fake();

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertQueued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->ticket->is($this->working) && str_contains($mail->subjectLine, 'SLA Reminder'));
        Mail::assertNotQueued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->ticket->is($this->pending));
    }
}
