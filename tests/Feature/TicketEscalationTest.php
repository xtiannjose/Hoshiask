<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketEscalationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_escalate_a_ticket_to_another_staff_member(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate@example.com', User::ROLE_ADMIN);
        $technical = $this->createAssignedSupportUser('Escalation Target', 'escalation-target@example.com');
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), [
                'escalated_to' => $technical->id,
                'reason' => 'Needs specialist review',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertTrue($ticket->isEscalated());
        $this->assertSame($technical->id, $ticket->escalated_to);
        $this->assertSame($admin->id, $ticket->escalated_by);
        $this->assertSame('Needs specialist review', $ticket->escalation_reason);
        $this->assertTrue($ticket->replies()->where('is_internal', true)->exists());
    }

    public function test_cannot_escalate_a_ticket_to_self(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate-self@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), [
                'escalated_to' => $admin->id,
                'reason' => 'Trying to self-escalate',
            ])
            ->assertSessionHasErrors('escalated_to');

        $this->assertFalse($ticket->fresh()->isEscalated());
    }

    public function test_cannot_escalate_a_ticket_to_a_client(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate-client@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $otherClient = $this->createClient('Other Client', 'other-escalate-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), [
                'escalated_to' => $otherClient->id,
                'reason' => 'Invalid target',
            ])
            ->assertSessionHasErrors('escalated_to');
    }

    public function test_escalation_reason_is_required(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate-reason@example.com', User::ROLE_ADMIN);
        $technical = $this->createAssignedSupportUser('Reason Target', 'reason-target@example.com');
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate', $ticket), ['escalated_to' => $technical->id])
            ->assertSessionHasErrors('reason');
    }

    public function test_admin_can_clear_an_escalation(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate-clear@example.com', User::ROLE_ADMIN);
        $technical = $this->createAssignedSupportUser('Clear Target', 'clear-target@example.com');
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)->post(route('admin.tickets.escalate', $ticket), [
            'escalated_to' => $technical->id,
            'reason' => 'Needs specialist review',
        ]);

        $this->assertTrue($ticket->fresh()->isEscalated());

        $this->actingAs($admin)
            ->post(route('admin.tickets.escalate.clear', $ticket))
            ->assertRedirect();

        $this->assertFalse($ticket->fresh()->isEscalated());
        $this->assertNotNull($ticket->fresh()->escalation_resolved_at);
    }

    public function test_escalated_badge_is_visible_to_both_admin_and_the_originating_client(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escalate-badge@example.com', User::ROLE_ADMIN);
        $technical = $this->createAssignedSupportUser('Badge Target', 'badge-target@example.com');
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($admin)->post(route('admin.tickets.escalate', $ticket), [
            'escalated_to' => $technical->id,
            'reason' => 'Needs specialist review',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Escalated');

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Escalated for further review');
    }
}
