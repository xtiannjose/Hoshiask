<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketReplyEditWindowTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_client_can_edit_and_delete_a_recent_message(): void
    {
        [$client, $ticket] = $this->clientTicket();
        $reply = $this->replyBy($client, $ticket, minutesAgo: 30);

        $this->actingAs($client)
            ->patchJson(route('client.tickets.replies.update', [$ticket, $reply]), ['message' => 'Edited text'])
            ->assertOk()
            ->assertJsonPath('message', 'Message edited');
        $this->assertSame('Edited text', $reply->fresh()->message);

        $this->actingAs($client)
            ->deleteJson(route('client.tickets.replies.delete', [$ticket, $reply]))
            ->assertOk()
            ->assertJsonPath('message', 'Message deleted');
        $this->assertNotNull($reply->fresh()->deleted_at);
    }

    public function test_client_cannot_edit_or_delete_after_the_default_three_hour_window(): void
    {
        [$client, $ticket] = $this->clientTicket();
        $reply = $this->replyBy($client, $ticket, minutesAgo: 181);

        $this->actingAs($client)
            ->patchJson(route('client.tickets.replies.update', [$ticket, $reply]), ['message' => 'Too late'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Messages can only be edited within 3 hours.');

        $this->actingAs($client)
            ->deleteJson(route('client.tickets.replies.delete', [$ticket, $reply]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Messages can only be deleted within 3 hours.');

        $this->assertNull($reply->fresh()->deleted_at);
    }

    public function test_edit_window_is_configurable(): void
    {
        config(['hoshiask.reply_edit_window_minutes' => 30]);
        [$client, $ticket] = $this->clientTicket();
        $reply = $this->replyBy($client, $ticket, minutesAgo: 45);

        $this->actingAs($client)
            ->patchJson(route('client.tickets.replies.update', [$ticket, $reply]), ['message' => 'Too late'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Messages can only be edited within 30 minutes.');

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-edit-window-ms="1800000"', false)
            ->assertSee('data-edit-window-label="30 minutes"', false);
    }

    public function test_staff_edits_are_not_limited_by_the_client_window(): void
    {
        [$client, $ticket] = $this->clientTicket();
        $technical = $this->createAssignedSupportUser();
        $ticket->update(['assigned_to' => $technical->id]);
        $ticket->assignedUsers()->syncWithoutDetaching([$technical->id]);
        $reply = $this->replyBy($technical, $ticket, minutesAgo: 600);

        $this->actingAs($technical)
            ->patchJson(route('admin.tickets.replies.update', [$ticket, $reply]), ['message' => 'Staff correction'])
            ->assertOk();
        $this->assertSame('Staff correction', $reply->fresh()->message);
    }

    public function test_reply_from_another_ticket_is_rejected(): void
    {
        [$client, $ticket] = $this->clientTicket();
        $otherTicket = $this->createTicket($client, $this->createCategory('Other Category'));
        $reply = $this->replyBy($client, $otherTicket, minutesAgo: 5);

        $this->actingAs($client)
            ->patchJson(route('client.tickets.replies.update', [$ticket, $reply]), ['message' => 'Wrong ticket'])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Ticket}
     */
    private function clientTicket(): array
    {
        $client = $this->createClient();

        return [$client, $this->createTicket($client, $this->createCategory())];
    }

    private function replyBy(User $author, Ticket $ticket, int $minutesAgo): TicketReply
    {
        $this->travelTo(now()->subMinutes($minutesAgo));
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'message' => 'Original text',
            'is_internal' => false,
        ]);
        $this->travelBack();

        return $reply;
    }
}
