<?php

namespace Tests\Feature;

use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class CancellationReasonTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_client_cancellation_stores_the_reason_on_the_ticket(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'Filed by mistake'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertSame('Filed by mistake', $ticket->cancellation_reason);
    }

    public function test_staff_status_change_to_cancelled_stores_the_reason(): void
    {
        $admin = $this->createUser('Admin User', 'admin-cancel-reason@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'pending']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), ['status' => 'cancelled', 'close_reason' => '  Duplicate of TK-100  ']);

        $ticket->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertSame('Duplicate of TK-100', $ticket->cancellation_reason);
    }

    public function test_quick_update_to_cancelled_stores_the_reason(): void
    {
        $admin = $this->createUser('Admin User', 'admin-quick-cancel@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.quick-update', $ticket), [
                'assigned_to' => '',
                'status' => 'cancelled',
                'priority' => 'medium',
                'close_reason' => 'Requester withdrew the request',
            ]);

        $this->assertSame('Requester withdrew the request', $ticket->fresh()->cancellation_reason);
    }

    public function test_bulk_cancel_stores_the_reason_on_every_selected_ticket(): void
    {
        $admin = $this->createUser('Admin User', 'admin-bulk-cancel@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $first = $this->createTicket($client, $category, ['status' => 'open']);
        $second = $this->createTicket($client, $category, ['status' => 'pending', 'subject' => 'Second']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'status',
                'status' => 'cancelled',
                'selected_ids' => [$first->id, $second->id],
                'close_reason' => 'Test tickets',
            ]);

        $this->assertSame('Test tickets', $first->fresh()->cancellation_reason);
        $this->assertSame('Test tickets', $second->fresh()->cancellation_reason);

        foreach ([$first, $second] as $ticket) {
            $this->assertSame(1, TicketReply::where('ticket_id', $ticket->id)
                ->where('is_internal', true)
                ->where('message', "Ticket was cancelled by {$admin->name}.
Reason: Test tickets")
                ->count());
        }
    }

    public function test_reopening_a_cancelled_ticket_clears_the_reason(): void
    {
        $admin = $this->createUser('Admin User', 'admin-reopen-reason@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Filed by mistake',
        ]);

        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'open']);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->cancelled_at);
        $this->assertNull($ticket->cancellation_reason);
    }

    public function test_the_reason_is_shown_to_both_staff_and_the_client(): void
    {
        $admin = $this->createUser('Admin User', 'admin-show-reason@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Filed by mistake',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Cancellation Reason')
            ->assertSee('Filed by mistake');

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Cancellation Reason')
            ->assertSee('Filed by mistake');
    }

    public function test_the_reason_is_escaped_when_displayed(): void
    {
        $admin = $this->createUser('Admin User', 'admin-escape-reason@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => '<script>alert(1)</script>',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_ticket_cancelled_before_reasons_were_stored_shows_not_recorded(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Not recorded');
    }

    public function test_an_active_ticket_has_no_cancellation_section(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Cancellation Reason');
    }

    public function test_a_cancelled_ticket_cannot_be_acknowledged_and_shows_no_acknowledge_button(): void
    {
        $superUser = $this->createSupportUser('Ack Super', 'ack-cancelled-super@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->actingAs($superUser)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Acknowledge Ticket');

        $this->actingAs($superUser)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.acknowledge', $ticket))
            ->assertSessionHas('error', 'Cancelled tickets do not need to be acknowledged.');

        // Viewing the ticket records a "seen" state, but it must not be acknowledged.
        $this->assertNull(
            TicketUserState::query()
                ->where('ticket_id', $ticket->id)
                ->where('user_id', $superUser->id)
                ->value('acknowledged_at')
        );
    }

    public function test_an_open_ticket_still_shows_the_acknowledge_button(): void
    {
        $superUser = $this->createSupportUser('Ack Super', 'ack-open-super@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $this->actingAs($superUser)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Acknowledge Ticket');
    }

    public function test_ticket_export_includes_the_reason_after_the_existing_columns(): void
    {
        $admin = $this->createUser('Admin User', 'admin-export-reason@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Filed by mistake',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('admin.tickets.export', ['tab' => 'history']))
            ->assertOk()
            ->streamedContent();

        $lines = array_values(array_filter(preg_split('/\r?\n/', $csv)));
        $this->assertStringEndsWith('"SLA State","Cancellation Reason","Field Office"', $lines[0]);
        $this->assertStringContainsString('Filed by mistake', $csv);
    }

    public function test_custom_report_detail_csv_includes_the_reason_and_neutralises_formulas(): void
    {
        $admin = $this->createUser('Admin User', 'admin-report-reason@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => '=HYPERLINK("http://example.com")',
        ]);

        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.custom.csv', ['layout' => 'detailed', 'status' => 'cancelled']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Cancellation Reason', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
