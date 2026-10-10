<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketCopyLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_index_renders_copy_link_action(): void
    {
        $supportUser = $this->createSupportUser();
        $ticket = $this->createTicket();

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $response->assertOk();
        $response->assertSee('data-ticket-copy-link', false);
        $response->assertSee('Copy link');
        $response->assertSee(route('admin.tickets.show', $ticket), false);
    }

    public function test_admin_ticket_partial_results_render_copy_link_action(): void
    {
        $supportUser = $this->createSupportUser();
        $ticket = $this->createTicket();

        $response = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'partial' => 1,
        ]));

        $response->assertOk();

        $html = (string) $response->json('html');
        $this->assertStringContainsString('data-ticket-copy-link', $html);
        $this->assertStringContainsString('Copy link', $html);
        $this->assertStringContainsString(route('admin.tickets.show', $ticket), $html);
    }

    private function createSupportUser(): User
    {
        return User::create([
            'name' => 'Copy Link Admin',
            'email' => 'copy-link-admin@example.com',
            'phone' => '09110000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createTicket(): Ticket
    {
        $client = User::create([
            'name' => 'Copy Link Client',
            'email' => 'copy-link-client@example.com',
            'phone' => '09112223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Copy Link Category',
            'description' => 'Copy link checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return Ticket::create([
            'name' => 'Copy Link Requester',
            'contact_number' => '09110000201',
            'email' => 'copy-link-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Copy link ticket',
            'description' => 'Copy link check.',
            'priority' => 'severity_2',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
    }
}
