<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientDashboardHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dashboard_heartbeat_token_changes_when_summary_changes(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Dashboard heartbeat open ticket',
            'status' => 'open',
            'ticket_number' => 'TK-DBOPEN1',
            'created_at' => Carbon::parse('2026-04-14 08:00:00'),
            'updated_at' => Carbon::parse('2026-04-14 08:00:00'),
        ]));

        $initialResponse = $this->actingAs($client)->getJson(route('client.dashboard', [
            'heartbeat' => '1',
        ]));

        $initialResponse->assertOk();
        $initialToken = (string) $initialResponse->json('token');
        $this->assertNotSame('', $initialToken);

        $this->travel(2)->seconds();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Dashboard heartbeat in progress ticket',
            'status' => 'in_progress',
            'ticket_number' => 'TK-DBPROG1',
            'created_at' => Carbon::parse('2026-04-14 08:05:00'),
            'updated_at' => Carbon::parse('2026-04-14 08:05:00'),
        ]));

        $nextResponse = $this->actingAs($client)->getJson(route('client.dashboard', [
            'heartbeat' => '1',
        ]));

        $nextResponse->assertOk();
        $this->assertNotSame($initialToken, (string) $nextResponse->json('token'));
    }

    private function createClientAndCategory(): array
    {
        config(['legal.require_acceptance' => false]);

        $client = User::create([
            'name' => 'Dashboard Client',
            'email' => 'dashboard-client@example.com',
            'phone' => '09189990009',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Dashboard Category',
            'description' => 'Dashboard category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$client, $category];
    }

    private function ticketPayload(User $client, Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => $client->name,
            'contact_number' => '09189990009',
            'email' => $client->email,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Dashboard ticket',
            'description' => 'Dashboard ticket description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ], $overrides);
    }
}
