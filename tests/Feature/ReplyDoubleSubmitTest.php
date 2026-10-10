<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Admin\TicketStatusWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ReplyDoubleSubmitTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_sending_the_same_client_reply_twice_stores_it_once_and_answers_both_times(): void
    {
        $client = $this->createClient('Double Client', 'double-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $first = $this->actingAs($client)->postJson(route('client.tickets.reply', $ticket), ['message' => 'Please help, it is urgent.']);
        $second = $this->actingAs($client)->postJson(route('client.tickets.reply', $ticket), ['message' => '  Please help,  it is urgent. ']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, TicketReply::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame($first->json('reply.id'), $second->json('reply.id'), 'the repeat must answer with the reply already saved');
        $this->assertSame(1, SystemLog::query()->where('event_type', 'ticket.reply.created')->count());
    }

    public function test_the_same_words_sent_again_after_half_a_minute_are_a_new_reply(): void
    {
        $client = $this->createClient('Later Client', 'later-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($client)->postJson(route('client.tickets.reply', $ticket), ['message' => 'Any news?'])->assertOk();
        $this->travel(31)->seconds();
        $this->actingAs($client)->postJson(route('client.tickets.reply', $ticket), ['message' => 'Any news?'])->assertOk();

        $this->assertSame(2, TicketReply::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_different_words_or_files_or_tickets_are_never_treated_as_a_repeat(): void
    {
        $client = $this->createClient('Distinct Client', 'distinct-client@example.com');
        $category = $this->createCategory();
        $one = $this->createTicket($client, $category);
        $two = $this->createTicket($client, $category, ['subject' => 'Second ticket']);

        $this->actingAs($client)->postJson(route('client.tickets.reply', $one), ['message' => 'Same words'])->assertOk();
        $this->actingAs($client)->postJson(route('client.tickets.reply', $one), ['message' => 'Other words'])->assertOk();
        $this->actingAs($client)->postJson(route('client.tickets.reply', $two), ['message' => 'Same words'])->assertOk();
        $this->actingAs($client)->postJson(route('client.tickets.reply', $one), [
            'message' => 'Same words',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 1, 'text/plain')],
        ])->assertOk();

        $this->assertSame(3, TicketReply::query()->where('ticket_id', $one->id)->count());
        $this->assertSame(1, TicketReply::query()->where('ticket_id', $two->id)->count());
    }

    public function test_sending_the_same_staff_reply_twice_stores_it_once(): void
    {
        $staff = $this->createUser('Double Staff', 'double-staff@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Staff Double Client', 'staff-double-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $first = $this->actingAs($staff)->postJson(route('admin.tickets.reply', $ticket), ['message' => 'We are looking into it.']);
        $second = $this->actingAs($staff)->postJson(route('admin.tickets.reply', $ticket), ['message' => 'We are looking into it.']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(1, TicketReply::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, SystemLog::query()->where('event_type', 'ticket.reply.created')->count());
    }

    public function test_a_public_staff_reply_sent_as_json_moves_an_open_ticket_to_in_progress(): void
    {
        $staff = $this->createUser('Json Staff', 'json-staff@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Json Client', 'json-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($staff)->postJson(route('admin.tickets.reply', $ticket), ['message' => 'On it.'])->assertOk();

        $this->assertSame('in_progress', DB::table('tickets')->where('id', $ticket->id)->value('status'));
    }

    public function test_the_status_workflow_refuses_an_empty_or_unknown_status(): void
    {
        $staff = $this->createUser('Guard Staff', 'guard-staff@example.com', User::ROLE_SUPER_USER);
        $ticket = $this->createTicket($this->createClient('Guard Client', 'guard-client@example.com'), $this->createCategory());
        $workflow = app(TicketStatusWorkflowService::class);
        $this->actingAs($staff);

        foreach (['', 'archived'] as $status) {
            try {
                $workflow->updateTicketStatus(Request::create('/', 'POST', ['status' => $status]), $ticket);
                $this->fail("Status '{$status}' should have been refused.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }

        $this->assertSame('open', DB::table('tickets')->where('id', $ticket->id)->value('status'));
    }

    public function test_a_client_reply_cannot_reopen_a_ticket_staff_closed_a_moment_earlier(): void
    {
        $client = $this->createClient('Race Client', 'race-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'resolved', 'resolved_at' => now()->subDay()]);

        // Staff close the ticket after the client's request has loaded it but before the reply is saved:
        // the first time the ticket is read, the stored row is changed while the loaded copy stays "resolved".
        $changed = false;
        Ticket::retrieved(function (Ticket $loaded) use (&$changed): void {
            if (! $changed) {
                $changed = true;
                DB::table('tickets')->where('id', $loaded->id)->update(['status' => 'closed', 'closed_at' => now()]);
            }
        });

        $this->actingAs($client)
            ->postJson(route('client.tickets.reply', $ticket), ['message' => 'One more thing'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This ticket is closed and can no longer accept replies.');

        $this->assertSame(0, TicketReply::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame('closed', DB::table('tickets')->where('id', $ticket->id)->value('status'));
    }
}
