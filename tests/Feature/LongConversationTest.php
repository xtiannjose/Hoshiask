<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A ticket page renders only its newest 200 replies. It must say so and offer the rest, and the message count must
 * be the real one, instead of silently dropping the start of the conversation.
 */
class LongConversationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private const REPLIES = 205;

    public function test_staff_are_told_how_many_old_messages_are_hidden_and_can_show_them_all(): void
    {
        [$client, $staff, $ticket] = $this->ticketWithLongConversation();

        $page = $this->actingAs($staff)->get(route('admin.tickets.show', $ticket));

        $page->assertOk();
        $page->assertSee('The 5 oldest messages are not shown.');
        $page->assertSee('206 messages');
        $page->assertSee('Message 205 of the long conversation');
        $page->assertDontSee('Message 001 of the long conversation');
        $page->assertSee('all_messages=1', false);

        $all = $this->actingAs($staff)->get(route('admin.tickets.show', [$ticket, 'all_messages' => 1]));

        $all->assertOk();
        $all->assertDontSee('oldest messages are not shown');
        $all->assertSee('206 messages');
        $all->assertSee('Message 001 of the long conversation');
        $all->assertSee('Message 205 of the long conversation');
    }

    public function test_the_client_sees_the_same_notice_and_internal_notes_are_not_counted(): void
    {
        [$client, $staff, $ticket] = $this->ticketWithLongConversation();
        TicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'message' => 'Internal note the client must never count',
            'is_internal' => true,
        ]);

        $page = $this->actingAs($client)->get(route('client.tickets.show', $ticket));

        $page->assertOk();
        $page->assertSee('The 5 oldest messages are not shown.');
        $page->assertSee('206 messages');
        $page->assertDontSee('Internal note the client must never count');

        $this->actingAs($client)
            ->get(route('client.tickets.show', [$ticket, 'all_messages' => 1]))
            ->assertOk()
            ->assertDontSee('oldest messages are not shown')
            ->assertSee('Message 001 of the long conversation');
    }

    public function test_a_short_conversation_shows_no_notice(): void
    {
        $client = $this->createClient();
        $staff = $this->createSupportUser();
        $ticket = $this->createTicket($client, $this->createCategory());
        TicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'message' => 'Only reply',
            'is_internal' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('2 messages')
            ->assertDontSee('oldest messages are not shown');
    }

    /**
     * @return array{0: User, 1: User, 2: Ticket}
     */
    private function ticketWithLongConversation(): array
    {
        $client = $this->createClient();
        $staff = $this->createSupportUser();
        $ticket = $this->createTicket($client, $this->createCategory());

        TicketReply::unguarded(function () use ($ticket, $staff): void {
            for ($number = 1; $number <= self::REPLIES; $number++) {
                TicketReply::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $staff->id,
                    'message' => sprintf('Message %03d of the long conversation', $number),
                    'is_internal' => false,
                    'created_at' => now()->subMinutes(self::REPLIES - $number + 1),
                    'updated_at' => now()->subMinutes(self::REPLIES - $number + 1),
                ]);
            }
        });

        return [$client, $staff, $ticket];
    }
}
