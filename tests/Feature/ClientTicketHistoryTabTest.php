<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientTicketHistoryTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_history_tab_shows_only_resolved_and_closed_tickets(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Open ticket item',
            'status' => 'open',
            'ticket_number' => 'TK-HISOPEN1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Resolved ticket item',
            'status' => 'resolved',
            'ticket_number' => 'TK-HISRES1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Closed ticket item',
            'status' => 'closed',
            'ticket_number' => 'TK-HISCLO1',
        ]));

        $response = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('History');
        $response->assertSee('Resolved ticket item');
        $response->assertSee('Closed ticket item');
        $response->assertDontSee('Open ticket item');
    }

    public function test_client_history_tab_status_filter_handles_invalid_status_safely(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History resolved ticket',
            'status' => 'resolved',
            'ticket_number' => 'TK-HISRES2',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History closed ticket',
            'status' => 'closed',
            'ticket_number' => 'TK-HISCLO2',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History open ticket',
            'status' => 'open',
            'ticket_number' => 'TK-HISOPEN2',
        ]));

        $response = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'history',
            'status' => 'open',
        ]));

        $response->assertOk();
        $response->assertSee('History resolved ticket');
        $response->assertSee('History closed ticket');
        $response->assertDontSee('History open ticket');
    }

    public function test_client_tickets_tab_hides_resolved_and_closed_tickets_and_filters(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Tickets open ticket',
            'status' => 'open',
            'ticket_number' => 'TK-TABOPEN1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Tickets in progress ticket',
            'status' => 'in_progress',
            'ticket_number' => 'TK-TABPROG1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Tickets resolved ticket',
            'status' => 'resolved',
            'ticket_number' => 'TK-TABRES1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Tickets closed ticket',
            'status' => 'closed',
            'ticket_number' => 'TK-TABCLO1',
        ]));

        $ticketsResponse = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'tickets',
        ]));

        $ticketsResponse->assertOk();
        $ticketsResponse->assertSee('Tickets open ticket');
        $ticketsResponse->assertSee('Tickets in progress ticket');
        $ticketsResponse->assertDontSee('Tickets resolved ticket');
        $ticketsResponse->assertDontSee('Tickets closed ticket');
        $ticketsResponse->assertDontSee('value="resolved"', false);
        $ticketsResponse->assertDontSee('value="closed"', false);
        $ticketsResponse->assertDontSee('name="priority"', false);

        $invalidStatusResponse = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'tickets',
            'status' => 'resolved',
        ]));

        $invalidStatusResponse->assertOk();
        $invalidStatusResponse->assertSee('Tickets open ticket');
        $invalidStatusResponse->assertSee('Tickets in progress ticket');
        $invalidStatusResponse->assertDontSee('Tickets resolved ticket');
        $invalidStatusResponse->assertDontSee('Tickets closed ticket');
        $invalidStatusResponse->assertDontSee('name="priority"', false);
    }

    public function test_client_ticket_heartbeat_returns_token_and_changes_when_visible_scope_changes(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        $ticket = Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Heartbeat ticket item',
            'status' => 'open',
            'ticket_number' => 'TK-HEARTBEAT1',
        ]));

        $initialHeartbeatResponse = $this->actingAs($client)->getJson(route('client.tickets.index', [
            'tab' => 'tickets',
            'heartbeat' => '1',
        ]));

        $initialHeartbeatResponse->assertOk();
        $initialToken = (string) $initialHeartbeatResponse->json('token');
        $this->assertNotSame('', $initialToken);

        $this->travel(2)->seconds();
        $ticket->update([
            'subject' => 'Heartbeat ticket item updated',
        ]);

        $nextHeartbeatResponse = $this->actingAs($client)->getJson(route('client.tickets.index', [
            'tab' => 'tickets',
            'heartbeat' => '1',
        ]));

        $nextHeartbeatResponse->assertOk();
        $this->assertNotSame($initialToken, (string) $nextHeartbeatResponse->json('token'));
    }

    public function test_client_ticket_index_partial_request_returns_rendered_html_and_token(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Partial ticket item',
            'status' => 'open',
            'ticket_number' => 'TK-PARTIAL1',
        ]));

        $response = $this->actingAs($client)->getJson(route('client.tickets.index', [
            'tab' => 'tickets',
            'partial' => '1',
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'token']);
        $this->assertStringContainsString('Partial ticket item', $response->json('html'));
        $this->assertStringContainsString('data-client-tickets-results', $response->json('html'));
        $this->assertNotSame('', (string) $response->json('token'));
    }

    private function createClientAndCategory(): array
    {
        config(['legal.require_acceptance' => false]);

        $client = User::create([
            'name' => 'History Client',
            'email' => 'history-client@example.com',
            'phone' => '09189990003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'History Category',
            'description' => 'History category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$client, $category];
    }

    private function ticketPayload(User $client, Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => $client->name,
            'contact_number' => '09189990003',
            'email' => $client->email,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'History ticket',
            'description' => 'History ticket description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ], $overrides);
    }
}
