<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketResultSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_index_shows_result_count_summary(): void
    {
        $supportUser = $this->createSupportUser();
        $this->createTickets(3);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $response->assertOk();
        $response->assertSee('Showing', false);
        $response->assertSee('1-3', false);
        $response->assertSee('of', false);
        $response->assertSee('3', false);
        $response->assertSee('tickets', false);
    }

    public function test_admin_ticket_partial_results_include_result_count_summary(): void
    {
        $supportUser = $this->createSupportUser();
        $this->createTickets(2);

        $response = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'partial' => 1,
        ]));

        $response->assertOk();

        $html = (string) $response->json('html');
        $this->assertStringContainsString('Showing', $html);
        $this->assertStringContainsString('1-2', $html);
        $this->assertStringContainsString('of', $html);
        $this->assertStringContainsString('tickets', $html);
    }

    public function test_admin_ticket_index_shows_empty_result_summary(): void
    {
        $supportUser = $this->createSupportUser();

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $response->assertOk();
        $response->assertSee('No tickets match the current filters.');
    }

    private function createSupportUser(): User
    {
        return User::create([
            'name' => 'Result Summary Admin',
            'email' => 'result-summary-admin@example.com',
            'phone' => '09110000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createTickets(int $count): void
    {
        $client = User::create([
            'name' => 'Result Summary Client',
            'email' => 'result-summary-client@example.com',
            'phone' => '09112223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Result Summary Category',
            'description' => 'Result summary checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        for ($index = 1; $index <= $count; $index++) {
            Ticket::create([
                'name' => "Result Summary Requester {$index}",
                'contact_number' => '09110000202',
                'email' => "result-summary-requester-{$index}@example.com",
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => "Result summary ticket {$index}",
                'description' => 'Result summary check.',
                'priority' => 'severity_2',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
        }
    }
}
