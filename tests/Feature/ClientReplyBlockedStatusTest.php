<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientReplyBlockedStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedStatuses(): array
    {
        return [
            'closed' => ['closed'],
            'cancelled' => ['cancelled'],
        ];
    }

    private static function rejectionMessage(string $status): string
    {
        return $status === 'cancelled'
            ? 'This ticket was cancelled and can no longer accept replies.'
            : 'This ticket is closed and can no longer accept replies.';
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function openStatuses(): array
    {
        return [
            'open' => ['open'],
            'in progress' => ['in_progress'],
            'pending' => ['pending'],
            'resolved' => ['resolved'],
        ];
    }

    #[DataProvider('blockedStatuses')]
    public function test_client_reply_is_rejected_for_html_requests_on_a_blocked_status(string $status): void
    {
        [$client, $ticket] = $this->seedTicket($status);

        $this->actingAs($client)
            ->from(route('client.tickets.show', $ticket))
            ->post(route('client.tickets.reply', $ticket), ['message' => 'Reply that must be rejected'])
            ->assertRedirect(route('client.tickets.show', $ticket))
            ->assertSessionHas('error', self::rejectionMessage($status));

        $this->assertDatabaseMissing('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Reply that must be rejected',
        ]);
    }

    #[DataProvider('blockedStatuses')]
    public function test_client_reply_is_rejected_for_json_requests_on_a_blocked_status(string $status): void
    {
        [$client, $ticket] = $this->seedTicket($status);

        $this->actingAs($client)
            ->postJson(route('client.tickets.reply', $ticket), ['message' => 'Reply that must be rejected'])
            ->assertStatus(422)
            ->assertJson(['message' => self::rejectionMessage($status)]);

        $this->assertDatabaseCount('ticket_replies', 0);
    }

    #[DataProvider('openStatuses')]
    public function test_client_can_still_reply_on_an_active_or_resolved_ticket(string $status): void
    {
        [$client, $ticket] = $this->seedTicket($status);

        $this->actingAs($client)
            ->from(route('client.tickets.show', $ticket))
            ->post(route('client.tickets.reply', $ticket), ['message' => 'Reply that must be accepted'])
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Reply that must be accepted',
        ]);
    }

    #[DataProvider('blockedStatuses')]
    public function test_reply_form_is_replaced_by_a_notice_on_a_blocked_status(string $status): void
    {
        [$client, $ticket] = $this->seedTicket($status);

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('id="ticket-reply-form"', false)
            ->assertSee("This ticket is {$status}. Reply is disabled.");
    }

    public function test_reply_form_is_shown_on_an_active_ticket(): void
    {
        [$client, $ticket] = $this->seedTicket('open');

        $this->actingAs($client)
            ->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('id="ticket-reply-form"', false)
            ->assertDontSee('Reply is disabled.');
    }

    /**
     * @return array{0: User, 1: Ticket}
     */
    private function seedTicket(string $status): array
    {
        $client = User::create([
            'name' => 'Reply Status Client',
            'email' => 'reply-status-client@example.com',
            'phone' => '09123334444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General support',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Reply Status Owner',
            'contact_number' => '09123335555',
            'email' => 'reply-status-owner@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket for reply status rules',
            'description' => 'Ticket used to check which statuses accept client replies',
            'priority' => 'medium',
            'status' => $status,
            'user_id' => $client->id,
            'category_id' => $category->id,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
        ]);

        return [$client, $ticket];
    }
}
