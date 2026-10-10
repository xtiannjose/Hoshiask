<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketCancellationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_client_can_cancel_an_open_ticket_with_a_reason(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'No longer needed'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertTrue($ticket->replies()->where('is_internal', false)->exists());
    }

    public function test_client_cannot_cancel_without_a_reason(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), [])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_client_cannot_cancel_a_ticket_already_in_progress(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'Changed my mind'])
            ->assertRedirect();

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_client_cannot_cancel_another_clients_ticket(): void
    {
        $client = $this->createClient();
        $otherClient = $this->createClient('Other Client', 'other-client@example.com');
        $ticket = $this->createTicket($otherClient, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'Not mine'])
            ->assertNotFound();
    }

    public function test_admin_can_cancel_a_pending_ticket_via_status_update(): void
    {
        $admin = $this->createUser('Admin User', 'admin-cancel@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'pending']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'cancelled',
                'close_reason' => 'Duplicate request',
            ])
            ->assertRedirect();

        $this->assertSame('cancelled', $ticket->fresh()->status);
    }

    public function test_admin_cannot_cancel_a_ticket_already_in_progress(): void
    {
        $admin = $this->createUser('Admin User', 'admin-cancel2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'cancelled',
                'close_reason' => 'Trying anyway',
            ])
            ->assertRedirect();

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_admin_status_update_requires_a_reason_to_cancel(): void
    {
        $admin = $this->createUser('Admin User', 'admin-cancel3@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), ['status' => 'cancelled'])
            ->assertSessionHasErrors('close_reason');

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_cancelling_records_a_cancelled_at_timestamp(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'No longer needed']);

        $this->assertNotNull($ticket->fresh()->cancelled_at);
    }

    public function test_cancelled_ticket_shows_a_neutral_sla_badge_not_a_live_breach_countdown(): void
    {
        $this->travelTo(now()->subDays(10));
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'cancelled']);
        $this->travelBack();

        $ticket->update(['cancelled_at' => $ticket->created_at->copy()->addDay()]);

        $this->assertSame('border-slate-200 bg-slate-50 text-slate-600', $ticket->sla_badge_class);
        $this->assertStringStartsWith('Cancelled - ', (string) $ticket->sla_badge_label);
        $this->assertStringContainsString('24h', (string) $ticket->sla_badge_label);
    }

    public function test_admin_can_reopen_a_cancelled_ticket_and_cancelled_at_is_cleared(): void
    {
        $admin = $this->createUser('Admin User', 'admin-cancel-reopen@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), ['status' => 'open'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->cancelled_at);
    }

    public function test_cancelled_tickets_are_excluded_from_the_needs_attention_tab(): void
    {
        $admin = $this->createUser('Admin User', 'admin-attention@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'cancelled']);

        $response = $this->actingAs($admin)
            ->get(route('admin.tickets.index', ['tab' => 'attention']));

        $response->assertOk();
        $response->assertDontSee($ticket->ticket_number);
    }
}
