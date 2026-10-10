<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class StaffNotesOnClosedTicketsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function noteOnlyStatuses(): array
    {
        return [
            'closed' => ['closed'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('noteOnlyStatuses')]
    public function test_staff_reply_on_a_closed_or_cancelled_ticket_is_always_an_internal_note(string $status): void
    {
        Mail::fake();
        $admin = $this->createUser('Admin User', 'admin-notes@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => $status]);

        // The form does not send is_internal at all: the server must still keep it private.
        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Follow-up note for the record'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Follow-up note for the record',
            'is_internal' => true,
        ]);
        $this->assertSame($status, $ticket->fresh()->status);
    }

    #[DataProvider('noteOnlyStatuses')]
    public function test_an_explicit_public_reply_is_still_forced_internal_on_a_closed_or_cancelled_ticket(string $status): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-public@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => $status]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Trying to be public', 'is_internal' => '0'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Trying to be public',
            'is_internal' => true,
        ]);
    }

    #[DataProvider('noteOnlyStatuses')]
    public function test_the_client_never_sees_notes_added_after_the_ticket_ended(string $status): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-hidden@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => $status]);

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Staff-only remark']);

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Staff-only remark');

        $this->actingAs($client)
            ->getJson(route('client.tickets.replies.feed', $ticket))
            ->assertOk()
            ->assertDontSee('Staff-only remark');
    }

    public function test_no_client_email_is_sent_for_a_note_on_an_ended_ticket(): void
    {
        Mail::fake();
        $admin = $this->createUser('Admin User', 'admin-notes-mail@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'cancelled']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Quiet note']);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_staff_reply_on_an_open_ticket_can_still_be_public(): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-open@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Public answer']);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Public answer',
            'is_internal' => false,
        ]);
    }

    public function test_staff_reply_on_a_resolved_ticket_can_still_be_public(): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-resolved@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'resolved']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.reply', $ticket), ['message' => 'Anything else?']);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Anything else?',
            'is_internal' => false,
        ]);
    }

    #[DataProvider('noteOnlyStatuses')]
    public function test_composer_explains_and_locks_the_internal_note_on_an_ended_ticket(string $status): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-ui@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => $status]);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-notes-only-notice', false)
            ->assertSee("This ticket is {$status}. Anything you post here is saved as an internal note")
            ->assertSee('<input type="hidden" name="is_internal" value="1">', false);
    }

    public function test_composer_shows_no_notice_on_an_active_ticket(): void
    {
        $admin = $this->createUser('Admin User', 'admin-notes-ui-open@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $this->actingAs($admin)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('data-notes-only-notice', false)
            ->assertDontSee('<input type="hidden" name="is_internal" value="1">', false);
    }
}
