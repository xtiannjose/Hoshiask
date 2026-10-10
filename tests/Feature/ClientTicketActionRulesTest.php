<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The rulebook for what a client may do to their own ticket. Every status is tried against every action.
 * A cell is [error shown, status afterwards]: a null error means the action is allowed; a denied action
 * must leave the ticket exactly as it was and write no ticket audit entry.
 */
class ClientTicketActionRulesTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const NOT_RATEABLE = 'Only resolved tickets awaiting feedback can be rated.';

    private const NOT_REOPENABLE = 'Only resolved or closed tickets can be reopened.';

    private const NOT_CANCELLABLE = 'This ticket can only be cancelled while it is Open or Pending.';

    private const CLOSED_NO_RESOLVE = 'Closed tickets cannot be marked as resolved.';

    private const CANCELLED_NO_RESOLVE = "This ticket was cancelled and can't be marked as resolved.";

    private const CLOSED_NO_REPLY = 'This ticket is closed and can no longer accept replies.';

    private const CANCELLED_NO_REPLY = 'This ticket was cancelled and can no longer accept replies.';

    private const PAYLOADS = [
        'resolve' => ['resolve_confirmation' => '1', 'rating' => '5', 'comment' => 'Fixed, thank you.'],
        'rate' => ['rating' => '5', 'comment' => 'Good support.'],
        'reopen' => ['reopen_reason' => 'It came back.'],
        'cancel' => ['cancel_reason' => 'No longer needed.'],
        'reply' => ['message' => 'Any update on this?'],
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: ?string}>
     */
    public static function rules(): array
    {
        $table = [
            //              resolve                              rate                              reopen                              cancel                              reply
            'open' => [[null, 'resolved'], [self::NOT_RATEABLE], [self::NOT_REOPENABLE], [null, 'cancelled'], [null, 'open']],
            'in_progress' => [[null, 'resolved'], [self::NOT_RATEABLE], [self::NOT_REOPENABLE], [self::NOT_CANCELLABLE], [null, 'in_progress']],
            'pending' => [[null, 'resolved'], [self::NOT_RATEABLE], [self::NOT_REOPENABLE], [null, 'cancelled'], [null, 'pending']],
            // Resolving a resolved ticket is a harmless repeat; a reply to a resolved ticket reopens it.
            'resolved' => [[null, 'resolved'], [null, 'resolved'], [null, 'open'], [self::NOT_CANCELLABLE], [null, 'open']],
            'closed' => [[self::CLOSED_NO_RESOLVE], [self::NOT_RATEABLE], [null, 'open'], [self::NOT_CANCELLABLE], [self::CLOSED_NO_REPLY]],
            'cancelled' => [[self::CANCELLED_NO_RESOLVE], [self::NOT_RATEABLE], [self::NOT_REOPENABLE], [self::NOT_CANCELLABLE], [self::CANCELLED_NO_REPLY]],
        ];

        $cells = [];
        foreach ($table as $status => $row) {
            foreach (array_keys(self::PAYLOADS) as $i => $action) {
                $cells["{$status}: {$action}"] = [$status, $action, $row[$i][0], $row[$i][1] ?? null];
            }
        }

        return $cells;
    }

    #[DataProvider('rules')]
    public function test_each_client_action_on_each_status(string $status, string $action, ?string $error, ?string $statusAfter): void
    {
        $client = $this->createClient();
        $ticket = $this->ticketIn($status, $client);
        $before = $ticket->fresh()->getAttributes();

        $response = $this->actingAs($client)
            ->from(route('client.tickets.show', $ticket))
            ->post(route("client.tickets.{$action}", $ticket), self::PAYLOADS[$action]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHasNoErrors();
        $successKey = $action === 'reply' ? 'chat_success' : 'success';

        if ($error === null) {
            $response->assertSessionHas($successKey)->assertSessionMissing('error');
            $this->assertSame($statusAfter, $ticket->fresh()->status);

            return;
        }

        $response->assertSessionHas('error', $error)->assertSessionMissing($successKey);
        $this->assertSame($before, $ticket->fresh()->getAttributes(), 'A denied action must not touch the ticket.');
        $this->assertSame(0, TicketReply::query()->count());
        $this->assertSame(0, SystemLog::query()->where('event_type', 'like', 'ticket.%')->count());
    }

    public function test_a_cancelled_ticket_keeps_its_cancellation_when_resolve_is_attempted(): void
    {
        $client = $this->createClient();
        $ticket = $this->ticketIn('cancelled', $client);

        $this->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), self::PAYLOADS['resolve'])
            ->assertSessionHas('error', self::CANCELLED_NO_RESOLVE);

        $ticket->refresh();
        $this->assertSame('cancelled', $ticket->status);
        $this->assertNotNull($ticket->cancelled_at);
        $this->assertSame('Filed by mistake', $ticket->cancellation_reason);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->satisfaction_rating);
    }

    public function test_resolving_an_already_resolved_ticket_changes_nothing(): void
    {
        $client = $this->createClient();
        $ticket = $this->ticketIn('resolved', $client);
        $ticket->update(['satisfaction_rating' => 4, 'satisfaction_comment' => 'First answer stands.']);

        $this->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), self::PAYLOADS['resolve'])
            ->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame(4, $ticket->satisfaction_rating);
        $this->assertSame('First answer stands.', $ticket->satisfaction_comment);
        $this->assertSame(0, TicketReply::query()->count());
    }

    private function ticketIn(string $status, User $client): Ticket
    {
        $this->assertContains($status, Ticket::STATUSES);
        $finished = in_array($status, ['resolved', 'closed'], true);

        return $this->createTicket($client, $this->createCategory(), [
            'status' => $status,
            'resolved_at' => $finished ? now()->subHour() : null,
            'closed_at' => $status === 'closed' ? now()->subHour() : null,
            'cancelled_at' => $status === 'cancelled' ? now()->subHour() : null,
            'cancellation_reason' => $status === 'cancelled' ? 'Filed by mistake' : null,
        ]);
    }

    /** What a request still holds when a change is saved by someone else while it runs. */
    private function serveStaleCopyOf(Ticket $ticket): void
    {
        $staleCopy = Ticket::findOrFail($ticket->id);
        $this->app['router']->bind('ticket', fn () => clone $staleCopy);
    }

    public function test_a_client_cancel_does_not_overwrite_a_resolution_saved_a_moment_earlier(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'pending']);
        $this->serveStaleCopyOf($ticket);
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($client)
            ->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'No longer needed'])
            ->assertSessionHas('error', 'This ticket can only be cancelled while it is Open or Pending.');

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->cancellation_reason);
    }

    public function test_a_double_submitted_resolve_acts_once(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);
        $this->serveStaleCopyOf($ticket);

        foreach ([1, 2] as $submit) {
            $this->actingAs($client)->post(route('client.tickets.resolve', $ticket), ['resolve_confirmation' => '1', 'rating' => 5]);
        }

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertSame(1, TicketReply::where('ticket_id', $ticket->id)->where('message', 'Client marked this ticket as resolved.')->count());
        $this->assertSame(1, SystemLog::where('event_type', 'ticket.resolved_by_client')->count());
    }
}
