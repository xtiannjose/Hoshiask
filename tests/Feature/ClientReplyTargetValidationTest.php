<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientReplyTargetValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_reply_with_invalid_target_redirects_for_html_requests(): void
    {
        [$client, $ticket, $foreignReply] = $this->seedTicketContext();

        $response = $this->actingAs($client)
            ->from(route('client.tickets.show', $ticket))
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Reply attempt with invalid target',
                'reply_to_id' => $foreignReply->id,
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHas('error', 'Invalid reply target.');
        $this->assertDatabaseMissing('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Reply attempt with invalid target',
        ]);
    }

    public function test_client_reply_with_invalid_target_returns_json_for_api_requests(): void
    {
        [$client, $ticket, $foreignReply] = $this->seedTicketContext();

        $response = $this->actingAs($client)
            ->postJson(route('client.tickets.reply', $ticket), [
                'message' => 'Reply attempt with invalid target',
                'reply_to_id' => $foreignReply->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Invalid reply target.',
        ]);
    }

    private function seedTicketContext(): array
    {
        $client = User::create([
            'name' => 'Client Reply Target',
            'email' => 'client-reply-target@example.com',
            'phone' => '09122223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $otherClient = User::create([
            'name' => 'Other Client',
            'email' => 'other-client-reply-target@example.com',
            'phone' => '09122224444',
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
            'name' => 'Target Owner',
            'contact_number' => '09122225555',
            'email' => 'target-owner@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket for reply target validation',
            'description' => 'Main ticket description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $foreignTicket = Ticket::create([
            'name' => 'Foreign Owner',
            'contact_number' => '09122226666',
            'email' => 'foreign-owner@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Foreign ticket for invalid reply target',
            'description' => 'Foreign ticket description',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $otherClient->id,
            'category_id' => $category->id,
        ]);

        $foreignReply = TicketReply::create([
            'ticket_id' => $foreignTicket->id,
            'user_id' => $otherClient->id,
            'message' => 'Foreign reply message',
            'is_internal' => false,
        ]);

        return [$client, $ticket, $foreignReply];
    }
}
